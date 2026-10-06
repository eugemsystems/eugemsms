<?php

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\RegisterPaymentGatewayAction;
use Modules\Finance\Domain\DataObjects\RegisterPaymentGatewayData;
use Modules\Finance\Domain\Support\PesepayCrypto;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\PaymentIntent;
use Modules\Finance\Models\Receipt;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

const PAYAPI_KEY = 'abcdefghijklmnopqrstuvwxyz012345';

/**
 * A parent paying through the mobile API against a (faked) Pesepay: start, follow, and settle by
 * the gateway's callback.
 *
 * @return array{school: School, user: User, child: Student, crypto: PesepayCrypto}
 */
function payApiFixture(bool $gatewayActive = true): array
{
    config([
        'services.pesepay.integration_key' => 'integration-key-1', 'services.pesepay.encryption_key' => PAYAPI_KEY,
        'services.pesepay.base_url' => 'https://pesepay.test/api/payments-engine',
    ]);
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    Term::factory()->for($school)->for($year, 'academicYear')->current()->create();

    foreach (['journal' => 'JNL/{SEQ:6}', 'receipt' => 'RCT/{SEQ:6}'] as $type => $pattern) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: $type, pattern: $pattern));
    }
    Account::factory()->for($school)->liability()->system('credit_balance')->create();
    Account::factory()->for($school)->system('suspense')->create();

    app(RegisterPaymentGatewayAction::class)->execute(new RegisterPaymentGatewayData(
        schoolId: $school->id, driver: 'pesepay', name: 'Pesepay', credentials: json_encode(['integration_key' => 'integration-key-1', 'encryption_key' => PAYAPI_KEY]),
        supportedMethods: ['ecocash'], supportedCurrencies: ['USD'],
        settlementAccountId: Account::factory()->for($school)->create()->id, feeAccountId: Account::factory()->for($school)->create()->id,
        isDefault: true, isActive: $gatewayActive,
    ));

    $user = User::factory()->create(['tenant_id' => $school->tenant_id, 'name' => 'Mrs Moyo']);
    $user->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);
    $guardian = Guardian::factory()->for($school)->create(['user_id' => $user->id]);
    $child = Student::factory()->for($school)->create();
    StudentGuardian::factory()->create(['school_id' => $school->id, 'student_id' => $child->id, 'guardian_id' => $guardian->id]);

    return ['school' => $school, 'user' => $user, 'child' => $child, 'crypto' => new PesepayCrypto(PAYAPI_KEY)];
}

/**
 * @param  array<string, mixed>  $f
 */
function payApiFakeGateway(array $f, string $reference = 'PSP-100', string $confirmedStatus = 'PENDING'): void
{
    Http::fake(['pesepay.test/*' => function (Request $request) use ($f, $reference, $confirmedStatus) {
        if (str_contains($request->url(), 'check-payment')) {
            return Http::response(['payload' => $f['crypto']->encrypt([
                'referenceNumber' => $reference, 'transactionStatus' => $confirmedStatus, 'amountDetails' => ['amount' => '50.00', 'currencyCode' => 'USD'],
            ])]);
        }

        return Http::response(['payload' => $f['crypto']->encrypt(['referenceNumber' => $reference, 'redirectUrl' => 'https://pay.pesepay.test/'.$reference, 'transactionStatus' => 'PENDING'])]);
    }]);
}

/**
 * Pesepay's result callback: plain JSON, authenticated by the merchant's integration key.
 */
function payApiCallback(string $reference, string $status = 'SUCCESS'): string
{
    return (string) json_encode(['referenceNumber' => $reference, 'transactionStatus' => $status]);
}

/**
 * @param  array<string, string>  $headers
 */
function payApiDeliver(TestCase $test, string $body, string $key = 'integration-key-1'): TestResponse
{
    return $test->call('POST', '/api/v1/webhooks/payments/pesepay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => $key], $body);
}

it('lists the gateways a parent can pay with, hiding inactive ones', function (): void {
    $f = payApiFixture();
    Sanctum::actingAs($f['user'], ['*']);

    $data = $this->getJson('/api/v1/finance/payment-methods')->assertOk()->json('data');
    expect($data)->toHaveCount(1)->and($data[0]['methods'])->toBe(['ecocash'])->and($data[0]['currencies'])->toBe(['USD']);

    $f2 = payApiFixture(gatewayActive: false);
    Sanctum::actingAs($f2['user'], ['*']);
    expect($this->getJson('/api/v1/finance/payment-methods')->json('data'))->toBe([]);
});

it('starts a hosted-checkout payment, needs an Idempotency-Key, and a repeat returns the same intent', function (): void {
    $f = payApiFixture();
    payApiFakeGateway($f);
    Sanctum::actingAs($f['user'], ['*']);
    $body = ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD'];

    $this->postJson('/api/v1/finance/payments', $body)->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');

    $first = $this->postJson('/api/v1/finance/payments', $body, ['Idempotency-Key' => 'k-1'])->assertStatus(201);
    expect($first->json('data.status'))->toBe('pending')
        ->and($first->json('data.checkout_url'))->toBe('https://pay.pesepay.test/PSP-100')
        ->and($first->json('data.amount.amount_minor'))->toBe(5000);

    $second = $this->postJson('/api/v1/finance/payments', $body, ['Idempotency-Key' => 'k-1']);
    expect($second->json('data.id'))->toBe($first->json('data.id'))->and(PaymentIntent::count())->toBe(1);
    Http::assertSentCount(1);
});

it('starts an EcoCash push for a phone number and tells the parent to approve it', function (): void {
    $f = payApiFixture();
    payApiFakeGateway($f, 'PSP-200');
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 2500, 'currency' => 'USD', 'method' => 'ecocash', 'phone' => '0771234567'], ['Idempotency-Key' => 'k-2'])->assertStatus(201);

    expect($response->json('data.method'))->toBe('ecocash')->and($response->json('data.instructions'))->toContain('0771234567')->and($response->json('data.checkout_url'))->toBeNull();
});

it('refuses another family\'s learner, a bad amount, an unsupported method and an unreachable gateway', function (): void {
    $f = payApiFixture();
    Http::fake(['pesepay.test/*' => Http::response(['message' => 'down'], 503)]);
    $other = Student::factory()->for($f['school'])->create();
    Sanctum::actingAs($f['user'], ['*']);
    $h = ['Idempotency-Key' => 'k-3'];

    $this->postJson('/api/v1/finance/payments', ['student' => $other->ulid, 'amount_minor' => 5000, 'currency' => 'USD'], $h)->assertStatus(404);
    $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5, 'currency' => 'USD'], ['Idempotency-Key' => 'k-4'])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD', 'method' => 'visa', 'phone' => '0771234567'], ['Idempotency-Key' => 'k-5'])->assertStatus(422);

    $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD'], ['Idempotency-Key' => 'k-6'])->assertStatus(502)->assertJsonPath('error.code', 'GATEWAY_UNAVAILABLE');
    expect(PaymentIntent::count())->toBe(0);
});

it('settles a payment from the gateway callback exactly once and shows the receipt to the parent', function (): void {
    $f = payApiFixture();
    payApiFakeGateway($f, 'PSP-300', 'SUCCESS');
    Sanctum::actingAs($f['user'], ['*']);
    $id = $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD'], ['Idempotency-Key' => 'k-7'])->json('data.id');

    $callback = payApiCallback('PSP-300');
    payApiDeliver($this, $callback)
        ->assertOk()->assertJsonPath('data.processing_status', 'processed');

    $intent = PaymentIntent::withoutGlobalScopes()->where('ulid', $id)->firstOrFail();
    expect($intent->status)->toBe('succeeded')->and($intent->receipt_id)->not->toBeNull()
        ->and(Receipt::withoutGlobalScopes()->count())->toBe(1);

    payApiDeliver($this, $callback)->assertOk();
    expect(Receipt::withoutGlobalScopes()->count())->toBe(1)->and(GatewayWebhook::count())->toBe(1);

    $shown = $this->getJson('/api/v1/finance/payments/'.$id)->assertOk();
    expect($shown->json('data.status'))->toBe('succeeded')->and($shown->json('data.receipt_issued'))->toBeTrue();
});

it('records a forged or unmatched callback as failed and settles nothing', function (): void {
    $f = payApiFixture();
    payApiFakeGateway($f, 'PSP-400');
    Sanctum::actingAs($f['user'], ['*']);
    $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD'], ['Idempotency-Key' => 'k-8']);

    $forged = payApiCallback('PSP-400');
    payApiDeliver($this, $forged, 'not-the-key')->assertOk()->assertJsonPath('data.processing_status', 'failed');

    $unknown = payApiCallback('PSP-NOPE');
    payApiDeliver($this, $unknown)->assertOk()->assertJsonPath('data.processing_status', 'failed');

    expect(PaymentIntent::withoutGlobalScopes()->first()->status)->toBe('pending')->and(Receipt::withoutGlobalScopes()->count())->toBe(0);
    $this->postJson('/api/v1/webhooks/payments/nonexistent', ['x' => 1])->assertStatus(404);
});

it('marks a payment failed when the gateway reports failure, and lets a parent see only their own payments', function (): void {
    $f = payApiFixture();
    payApiFakeGateway($f, 'PSP-500', 'FAILED');
    Sanctum::actingAs($f['user'], ['*']);
    $id = $this->postJson('/api/v1/finance/payments', ['student' => $f['child']->ulid, 'amount_minor' => 5000, 'currency' => 'USD'], ['Idempotency-Key' => 'k-9'])->json('data.id');

    payApiDeliver($this, payApiCallback('PSP-500', 'FAILED'))->assertOk();

    expect($this->getJson('/api/v1/finance/payments/'.$id)->json('data.status'))->toBe('failed');

    $stranger = User::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    $stranger->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);
    Sanctum::actingAs($stranger, ['*']);
    $this->getJson('/api/v1/finance/payments/'.$id)->assertStatus(404);
});
