<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Auth\OtpDeliveryChannel;
use Modules\Core\Domain\DataObjects\Auth\RequestOtpData;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Exceptions\OtpCooldownException;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-RequestOtp (Book A CORE-05 §4). This matters: a large share of
 * Zimbabwean parents will never set a password — phone + OTP is their
 * real login, not a fallback.
 */
final class RequestOtpAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly OtpDeliveryChannel $delivery,
    ) {}

    public function execute(RequestOtpData $data): void
    {
        $phone = PhoneNormalizer::toE164($data->phone);
        $scope = new ScopeChain(tenantId: $data->tenantId);

        if (Cache::has($this->cooldownKey($phone))) {
            throw new OtpCooldownException('Please wait before requesting another code.');
        }

        $user = User::where('tenant_id', $data->tenantId)->where('phone', $phone)->first();

        if ($user === null) {
            throw new InvalidCredentialsException('No account is registered to that phone number.');
        }

        $length = (int) $this->settings->get('auth.otp_length', $scope);
        $ttl = (int) $this->settings->get('auth.otp_ttl_seconds', $scope);
        $cooldown = (int) $this->settings->get('auth.otp_resend_cooldown_seconds', $scope);

        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        Cache::put($this->codeKey($phone), [
            'code' => $code,
            'attempts' => 0,
            'expires_at' => Carbon::now()->addSeconds($ttl)->timestamp,
        ], $ttl);
        Cache::put($this->cooldownKey($phone), true, $cooldown);

        $this->delivery->send($phone, $code);
    }

    private function codeKey(string $phone): string
    {
        return "otp:code:{$phone}";
    }

    private function cooldownKey(string $phone): string
    {
        return "otp:cooldown:{$phone}";
    }
}
