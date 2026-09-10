<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\AuthResult;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Domain\DataObjects\Auth\VerifyOtpData;
use Modules\Core\Domain\Events\Auth\UserLoggedIn;
use Modules\Core\Domain\Events\Auth\UserLoginFailed;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Exceptions\InvalidOtpException;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Models\LoginAttempt;

/**
 * ACT-VerifyOtp (Book A CORE-05 §4/AC-CORE-05-001). Max 3 attempts per
 * requested code (Book A §10 `auth.otp_length` sibling default) — a
 * wrong guess consumes an attempt without a fresh code being issued.
 */
final class VerifyOtpAction extends Action
{
    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly IssueApiTokenAction $issueApiToken,
    ) {}

    public function execute(VerifyOtpData $data): AuthResult
    {
        $phone = PhoneNormalizer::toE164($data->phone);
        $key = "otp:code:{$phone}";
        $entry = Cache::get($key);

        $user = User::where('tenant_id', $data->tenantId)->where('phone', $phone)->first();

        if ($entry === null) {
            $this->recordAttempt($phone, $user, 'otp_expired_or_missing');

            throw new InvalidOtpException('This code has expired. Please request a new one.');
        }

        if ($entry['code'] !== $data->code) {
            $attempts = $entry['attempts'] + 1;
            $remainingSeconds = max(1, $entry['expires_at'] - Carbon::now()->timestamp);

            if ($attempts >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
            } else {
                Cache::put($key, [...$entry, 'attempts' => $attempts], $remainingSeconds);
            }

            $this->recordAttempt($phone, $user, 'otp_mismatch');

            throw new InvalidOtpException('That code is not correct.');
        }

        if ($user === null) {
            $this->recordAttempt($phone, null, 'no_account');

            throw new InvalidCredentialsException('No account is registered to that phone number.');
        }

        Cache::forget($key);

        $user->forceFill([
            'last_login_at' => Carbon::now(),
            'last_login_ip' => $data->ip,
            'phone_verified_at' => $user->phone_verified_at ?? Carbon::now(),
        ])->save();

        $this->recordAttempt($phone, $user, null, true);
        event(new UserLoggedIn($user, 'sanctum'));

        $tokens = $this->issueApiToken->execute(new IssueTokenData(
            userId: $user->id,
            device: $data->device,
            ip: $data->ip,
        ));

        return new AuthResult($user, requiresTwoFactor: false, tokens: $tokens);
    }

    private function recordAttempt(string $phone, ?User $user, ?string $reason, bool $successful = false): void
    {
        LoginAttempt::create([
            'identifier' => $phone,
            'user_id' => $user?->id,
            'guard' => 'sanctum',
            'was_successful' => $successful,
            'failure_reason' => $reason,
            'attempted_at' => Carbon::now(),
        ]);

        if (! $successful) {
            event(new UserLoginFailed($phone, $reason ?? 'unknown'));
        }
    }
}
