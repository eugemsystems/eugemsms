<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Exceptions\GatewayRequestFailedException;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Domain\Support\PesepayCrypto;
use Modules\Finance\Domain\Support\PesepayGatewayDriver;
use Modules\Finance\Models\PaymentIntent;

const PESEPAY_TEST_KEY = 'abcdefghijklmnopqrstuvwxyz012345';

/**
 * @return array{driver: PesepayGatewayDriver, crypto: PesepayCrypto, intent: PaymentIntent}
 */
function pesepayFixture(string $currency = 'USD'): array
{
    config([
        'services.pesepay.integration_key' => 'integration-key-1',
        'services.pesepay.encryption_key' => PESEPAY_TEST_KEY,
        'services.pesepay.base_url' => 'https://pesepay.test/api/payments-engine',
        'services.pesepay.result_url' => 'https://school.test/api/v1/webhooks/payments/pesepay',
    ]);
    $intent = PaymentIntent::factory()->create(['currency' => $currency, 'amount_minor' => 12550, 'reference' => 'PI/20260101/abc12345', 'payer_email' => 'parent@example.com']);
    SchoolContext::set(School::findOrFail($intent->school_id));

    return ['driver' => new PesepayGatewayDriver, 'crypto' => new PesepayCrypto(PESEPAY_TEST_KEY), 'intent' => $intent];
}

it('round-trips AES-256-CBC payloads and refuses what another key produced', function (): void {
    $crypto = new PesepayCrypto(PESEPAY_TEST_KEY);
    $cipher = $crypto->encrypt(['referenceNumber' => 'R1']);

    expect($crypto->decrypt($cipher))->toBe(['referenceNumber' => 'R1'])
        ->and((new PesepayCrypto('zyxwvutsrqponmlkjihgfedcba987654'))->decrypt($cipher))->toBeNull()
        ->and($crypto->decrypt('not-a-payload'))->toBeNull();
    expect(fn () => new PesepayCrypto('short'))->toThrow(GatewayRequestFailedException::class);
});

it('creates a hosted checkout, sending the amount as a decimal and the intent reference as merchantReference', function (): void {
    $f = pesepayFixture();
    Http::fake(['pesepay.test/*' => Http::response(['payload' => $f['crypto']->encrypt(['referenceNumber' => 'PSP-1', 'redirectUrl' => 'https://pay.pesepay.test/PSP-1', 'pollUrl' => 'https://pesepay.test/poll/PSP-1'])])]);

    $checkout = $f['driver']->createCheckout($f['intent']);

    expect($checkout->gatewayReference)->toBe('PSP-1')->and($checkout->checkoutUrl)->toBe('https://pay.pesepay.test/PSP-1');

    Http::assertSent(function (Request $request) use ($f): bool {
        $sent = $f['crypto']->decrypt($request['payload']);

        return $request->url() === 'https://pesepay.test/api/payments-engine/v1/payments/initiate'
            && $request->header('authorization')[0] === 'integration-key-1'
            && $sent['amountDetails'] === ['amount' => 125.5, 'currencyCode' => 'USD']
            && $sent['merchantReference'] === 'PI/20260101/abc12345'
            && $sent['resultUrl'] === 'https://school.test/api/v1/webhooks/payments/pesepay';
    });
});

it('starts an EcoCash push with the configured method code for the currency', function (): void {
    $f = pesepayFixture();
    Http::fake(['pesepay.test/*' => Http::response(['payload' => $f['crypto']->encrypt(['referenceNumber' => 'PSP-2', 'transactionStatus' => 'PENDING'])])]);

    $push = $f['driver']->createPush($f['intent'], '0771234567', 'ecocash');

    expect($push->gatewayReference)->toBe('PSP-2')->and($push->instructions)->toContain('0771234567');

    Http::assertSent(function (Request $request) use ($f): bool {
        $sent = $f['crypto']->decrypt($request['payload']);

        return str_ends_with($request->url(), '/v2/payments/make-payment')
            && $sent['paymentMethodCode'] === 'PZW211'
            && $sent['paymentMethodRequiredFields'] === ['customerPhoneNumber' => '0771234567'];
    });

    expect(fn () => $f['driver']->createPush($f['intent'], '0771234567', 'unknown-method'))->toThrow(InvalidArgumentException::class);
});

it('maps Pesepay transaction statuses onto the platform statuses when polling', function (string $pesepay, string $expected): void {
    $f = pesepayFixture();
    $f['intent']->update(['gateway_reference' => 'PSP-3']);
    Http::fake(['pesepay.test/*' => Http::response(['payload' => $f['crypto']->encrypt(['transactionStatus' => $pesepay, 'amountDetails' => ['currencyCode' => 'USD', 'transactionServiceFee' => '1.25']])])]);

    $result = $f['driver']->poll($f['intent']->fresh());

    expect($result->status)->toBe($expected)->and($result->feeMinor)->toBe(125)->and($result->isSettled())->toBe($expected === 'succeeded');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'check-payment') && $request['referenceNumber'] === 'PSP-3');
})->with([['SUCCESS', 'succeeded'], ['FAILED', 'failed'], ['CANCELLED', 'cancelled'], ['PENDING', 'pending'], ['PROCESSING', 'pending']]);

it('raises a coded gateway error when Pesepay refuses, is unreachable, or answers with something unreadable', function (): void {
    $f = pesepayFixture();

    Http::fake(['pesepay.test/*' => Http::response(['message' => 'Invalid key'], 401)]);
    expect(fn () => $f['driver']->createCheckout($f['intent']))->toThrow(GatewayRequestFailedException::class);

    Http::fake(['pesepay.test/*' => Http::response(['payload' => 'garbage'])]);
    expect(fn () => $f['driver']->createCheckout($f['intent']))->toThrow(GatewayRequestFailedException::class);

    Http::fake(['pesepay.test/*' => fn () => throw new ConnectionException('timeout')]);
    expect(fn () => $f['driver']->createCheckout($f['intent']))->toThrow(GatewayRequestFailedException::class);
});

it('authenticates a result callback by decryption, and refuses plaintext or foreign-key bodies', function (): void {
    $f = pesepayFixture();
    $body = json_encode(['payload' => $f['crypto']->encrypt([
        'referenceNumber' => 'PSP-4', 'transactionStatus' => 'SUCCESS', 'amountDetails' => ['amount' => '125.50', 'currencyCode' => 'USD', 'transactionServiceFee' => '2.00'],
    ])]);

    expect($f['driver']->verifyWebhook([], $body))->toBeTrue();

    $event = $f['driver']->parseWebhook([], $body);
    expect($event->gatewayReference)->toBe('PSP-4')->and($event->isSettlement())->toBeTrue()
        ->and($event->amountMinor)->toBe(12550)->and($event->currency)->toBe('USD')->and($event->feeMinor)->toBe(200);

    $plain = json_encode(['referenceNumber' => 'PSP-4', 'transactionStatus' => 'SUCCESS']);
    $foreign = json_encode(['payload' => (new PesepayCrypto('zyxwvutsrqponmlkjihgfedcba987654'))->encrypt(['referenceNumber' => 'PSP-4', 'transactionStatus' => 'SUCCESS'])]);

    expect($f['driver']->verifyWebhook([], $plain))->toBeFalse()
        ->and($f['driver']->verifyWebhook([], $foreign))->toBeFalse()
        ->and($f['driver']->verifyWebhook([], 'not json'))->toBeFalse();
    expect(fn () => $f['driver']->parseWebhook([], $plain))->toThrow(InvalidArgumentException::class);
});

it('reports itself down without credentials and is registered for the pesepay driver key', function (): void {
    config(['services.pesepay.integration_key' => '', 'services.pesepay.encryption_key' => '']);

    expect((new PesepayGatewayDriver)->healthCheck()->status)->toBe('down')
        ->and(app(PaymentGatewayDriverRegistry::class)->resolve('pesepay'))->toBeInstanceOf(PesepayGatewayDriver::class);
});

it('reads the reference from pollUrl when the sandbox answers with referenceNumber null', function (): void {
    $f = pesepayFixture();
    Http::fake(['pesepay.test/*' => Http::response(['payload' => $f['crypto']->encrypt([
        'referenceNumber' => null,
        'transactionStatus' => 'SUCCESS',
        'pollUrl' => 'https://api.test.sandbox.pesepay.com/payments-engine/v1/payments/check-payment?referenceNumber=20261006142714125-A05E4612',
    ])])]);

    $push = $f['driver']->createPush($f['intent'], '0777777777', 'ecocash');

    expect($push->gatewayReference)->toBe('20261006142714125-A05E4612');
});
