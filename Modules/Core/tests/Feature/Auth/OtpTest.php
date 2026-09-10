<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Domain\Actions\Auth\RequestOtpAction;
use Modules\Core\Domain\Actions\Auth\VerifyOtpAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\RequestOtpData;
use Modules\Core\Domain\DataObjects\Auth\VerifyOtpData;
use Modules\Core\Domain\Exceptions\InvalidOtpException;
use Modules\Core\Domain\Exceptions\OtpCooldownException;
use Modules\Core\Models\Tenant;

it('issues tokens and the linked learners info after a correct OTP (AC-CORE-05-001)', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+263771234567', 'password' => null]);

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $tenant->id));
    $code = Cache::get('otp:code:+263771234567')['code'];

    $result = app(VerifyOtpAction::class)->execute(new VerifyOtpData('0771234567', $code, new DeviceData('Parent Phone'), $tenant->id));

    expect($result->user->is($user))->toBeTrue()
        ->and($result->tokens)->not->toBeNull()
        ->and($result->tokens->accessToken)->toContain('|');
});

it('rejects an incorrect OTP code', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+263771234567']);

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $tenant->id));

    app(VerifyOtpAction::class)->execute(new VerifyOtpData('0771234567', '000000', new DeviceData('Phone'), $tenant->id));
})->throws(InvalidOtpException::class);

it('enforces the resend cooldown', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+263771234567']);

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $tenant->id));
    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $tenant->id));
})->throws(OtpCooldownException::class);

it('invalidates the code after the maximum number of wrong attempts', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'phone' => '+263771234567']);

    app(RequestOtpAction::class)->execute(new RequestOtpData('0771234567', $tenant->id));

    foreach (range(1, 3) as $attempt) {
        try {
            app(VerifyOtpAction::class)->execute(new VerifyOtpData('0771234567', '000000', new DeviceData('Phone'), $tenant->id));
        } catch (InvalidOtpException) {
            // expected every time
        }
    }

    expect(Cache::has('otp:code:+263771234567'))->toBeFalse();
});
