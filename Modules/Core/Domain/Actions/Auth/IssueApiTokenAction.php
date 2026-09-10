<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Domain\DataObjects\Auth\TokenPair;
use Modules\Core\Domain\Events\Auth\TokenIssued;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\RefreshToken;

/**
 * ACT-IssueApiToken (Book A CORE-05 §3/§4). BR-CORE-05-010: a user may
 * hold at most `auth.max_devices_per_user` active devices; issuing
 * beyond the limit revokes the least recently used one first.
 */
final class IssueApiTokenAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(IssueTokenData $data): TokenPair
    {
        $user = User::findOrFail($data->userId);
        $scope = new ScopeChain(userId: $user->id, schoolId: $data->schoolId, tenantId: $user->tenant_id);

        return $this->transaction(function () use ($user, $data, $scope): TokenPair {
            $this->enforceDeviceLimit($user, $scope);

            $accessTtl = (int) $this->settings->get('auth.access_token_ttl_minutes', $scope);
            $refreshTtl = (int) $this->settings->get('auth.refresh_token_ttl_days', $scope);
            $accessExpiresAt = Carbon::now()->addMinutes($accessTtl);

            $new = $user->createToken($data->device->name, $data->abilities, $accessExpiresAt);

            $new->accessToken->forceFill([
                'school_id' => $data->schoolId,
                'device_id' => $data->device->id,
                'device_platform' => $data->device->platform,
                'device_model' => $data->device->model,
                'app_version' => $data->device->appVersion,
                'last_used_at' => Carbon::now(),
                'last_used_ip' => $data->ip,
            ])->save();

            $refreshExpiresAt = Carbon::now()->addDays($refreshTtl);
            $plainRefreshToken = Str::random(80);

            RefreshToken::create([
                'user_id' => $user->id,
                'access_token_id' => $new->accessToken->id,
                'token_hash' => hash('sha256', $plainRefreshToken),
                'device_id' => $data->device->id ?? $data->device->name,
                'expires_at' => $refreshExpiresAt,
            ]);

            event(new TokenIssued($user, $new->accessToken->id));

            return new TokenPair($new->plainTextToken, $plainRefreshToken, $accessExpiresAt, $refreshExpiresAt, $data->abilities);
        });
    }

    private function enforceDeviceLimit(User $user, ScopeChain $scope): void
    {
        $maxDevices = (int) $this->settings->get('auth.max_devices_per_user', $scope);

        $activeTokens = $user->tokens()
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()))
            ->orderBy('last_used_at')
            ->get();

        if ($activeTokens->count() < $maxDevices) {
            return;
        }

        $leastRecentlyUsed = $activeTokens->first();
        $leastRecentlyUsed?->forceFill(['revoked_at' => Carbon::now()])->save();

        RefreshToken::where('access_token_id', $leastRecentlyUsed?->id)->update(['used_at' => Carbon::now()]);
    }
}
