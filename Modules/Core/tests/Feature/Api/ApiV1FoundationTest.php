<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Domain\Actions\Auth\RequestOtpAction;
use Modules\Core\Domain\DataObjects\Auth\RequestOtpData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * Volume 1 §9: the `/api/v1` envelope, authentication family, error codes and idempotency.
 *
 * @return array{school: School, user: User}
 */
function apiV1Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create(['tenant_id' => $school->tenant_id, 'phone' => '+263771234567']);
    $user->schools()->attach($school, ['status' => 'active', 'is_primary' => true]);

    return compact('school', 'user');
}

it('wraps a successful response in the success/data/meta envelope with a request id', function (): void {
    $f = apiV1Fixture();
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->getJson('/api/v1/me')->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('data.active_school_id'))->toBe($f['school']->id)
        ->and($response->json('data.session.academic_year.id'))->not->toBeNull()
        ->and($response->json('meta.request_id'))->toStartWith('req_')
        ->and($response->json('meta.timestamp'))->toEndWith('Z');
});

it('answers an unauthenticated request with the UNAUTHENTICATED envelope, not a redirect', function (): void {
    apiV1Fixture();

    $response = $this->getJson('/api/v1/me')->assertStatus(401);

    expect($response->json('success'))->toBeFalse()->and($response->json('error.code'))->toBe('UNAUTHENTICATED');
});

it('refuses a token without the required ability with INSUFFICIENT_SCOPE', function (): void {
    $f = apiV1Fixture();
    Sanctum::actingAs($f['user'], ['notices.read']);

    $response = $this->getJson('/api/v1/guardians/me/children')->assertStatus(403);

    expect($response->json('error.code'))->toBe('INSUFFICIENT_SCOPE');
});

it('rejects a school the user is not assigned to with a coded error', function (): void {
    $f = apiV1Fixture();
    $other = School::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    Sanctum::actingAs($f['user'], ['*']);

    $response = $this->getJson('/api/v1/me', ['X-School-Id' => (string) $other->id])->assertStatus(403);

    expect($response->json('success'))->toBeFalse()->and($response->json('error.code'))->toBeString();
});

it('lists only the schools the user is assigned to', function (): void {
    $f = apiV1Fixture();
    School::factory()->create(['tenant_id' => $f['school']->tenant_id]);
    Sanctum::actingAs($f['user'], ['*']);

    $data = $this->getJson('/api/v1/me/schools')->assertOk()->json('data');

    expect($data)->toHaveCount(1)->and($data[0]['id'])->toBe($f['school']->id);
});

it('signs a parent in with a phone OTP, then refreshes the token pair and rejects a reused refresh token', function (): void {
    $f = apiV1Fixture();
    $device = ['name' => 'Parent Phone', 'id' => 'dev-1', 'platform' => 'android'];

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $f['school']->tenant_id));
    $code = Cache::get('otp:code:+263771234567')['code'];

    $tokens = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0771234567', 'code' => $code, 'device' => $device])->assertOk()->json('data');
    expect($tokens['access_token'])->toContain('|')->and($tokens['token_type'])->toBe('Bearer');

    $this->withToken($tokens['access_token'])->getJson('/api/v1/me')->assertOk();

    $refreshed = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertOk()->json('data');
    expect($refreshed['refresh_token'])->not->toBe($tokens['refresh_token']);

    $reused = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertStatus(403);
    expect($reused->json('success'))->toBeFalse()->and($reused->json('error.code'))->toBeString();

    app('auth')->forgetGuards();
    $this->withToken($tokens['access_token'])->getJson('/api/v1/me')->assertStatus(401);
});

it('rejects a wrong OTP and a missing device with coded errors', function (): void {
    $f = apiV1Fixture();
    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $f['school']->tenant_id));

    $wrong = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0771234567', 'code' => '000000', 'device' => ['name' => 'Phone']]);
    expect($wrong->json('success'))->toBeFalse()->and($wrong->json('error.code'))->toBeString();

    $invalid = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '0771234567', 'code' => '123456'])->assertStatus(422);
    expect($invalid->json('error.code'))->toBe('VALIDATION_FAILED')->and($invalid->json('error.details.errors'))->toHaveKey('device');
});

it('signs a staff member in with a password, but sends a 2FA account to the OTP flow', function (): void {
    $f = apiV1Fixture();
    $plain = User::factory()->create(['tenant_id' => $f['school']->tenant_id, 'email' => 'staff@example.com', 'password' => 'secret-pass-1']);
    $secured = User::factory()->create(['tenant_id' => $f['school']->tenant_id, 'email' => 'secure@example.com', 'password' => 'secret-pass-1', 'two_factor_confirmed_at' => now()]);
    $device = ['name' => 'Tablet'];

    $this->postJson('/api/v1/auth/login', ['identifier' => $plain->email, 'password' => 'secret-pass-1', 'device' => $device])->assertOk();

    $blocked = $this->postJson('/api/v1/auth/login', ['identifier' => $secured->email, 'password' => 'secret-pass-1', 'device' => $device])->assertStatus(403);
    expect($blocked->json('error.code'))->toBe('TWO_FACTOR_REQUIRED');

    $bad = $this->postJson('/api/v1/auth/login', ['identifier' => $plain->email, 'password' => 'wrong', 'device' => $device]);
    expect($bad->json('success'))->toBeFalse();
});

it('revokes the current token on logout', function (): void {
    $f = apiV1Fixture();
    $user = User::factory()->create(['tenant_id' => $f['school']->tenant_id, 'email' => 'out@example.com', 'password' => 'secret-pass-1']);
    $user->schools()->attach($f['school'], ['status' => 'active', 'is_primary' => true]);

    $token = $this->postJson('/api/v1/auth/login', ['identifier' => 'out@example.com', 'password' => 'secret-pass-1', 'device' => ['name' => 'Phone']])->json('data.access_token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
});

it('requires an Idempotency-Key on a mutation, replays a repeat, and refuses a key reused for a different body', function (): void {
    $f = apiV1Fixture();
    $calls = 0;
    Route::middleware(['api', 'auth:sanctum', 'serp.idempotent'])->post('/__test/pay', function () use (&$calls) {
        $calls++;

        return response()->json(['paid' => $calls]);
    });
    Sanctum::actingAs($f['user'], ['*']);

    $this->postJson('/__test/pay', ['amount' => 100])->assertStatus(400)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');

    $headers = ['Idempotency-Key' => 'abc-123'];
    $this->postJson('/__test/pay', ['amount' => 100], $headers)->assertOk()->assertJsonPath('paid', 1);
    $this->postJson('/__test/pay', ['amount' => 100], $headers)->assertOk()->assertJsonPath('paid', 1)->assertHeader('Idempotent-Replay', 'true');
    expect($calls)->toBe(1);

    $this->postJson('/__test/pay', ['amount' => 999], $headers)->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
});
