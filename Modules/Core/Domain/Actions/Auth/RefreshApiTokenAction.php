<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Domain\DataObjects\Auth\RefreshTokenData;
use Modules\Core\Domain\DataObjects\Auth\TokenPair;
use Modules\Core\Domain\Events\Auth\RefreshTokenReuseDetected;
use Modules\Core\Domain\Exceptions\InvalidTokenException;
use Modules\Core\Domain\Exceptions\TokenReuseDetectedException;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\RefreshToken;

/**
 * ACT-RefreshApiToken (Book A CORE-05 §4/BR-CORE-05-009/AC-CORE-05-002).
 * Single-use rotation: presenting an already-used refresh token revokes
 * the entire device token family and raises a security event, rather
 * than merely rejecting the one request.
 */
final class RefreshApiTokenAction extends Action
{
    public function __construct(
        private readonly IssueApiTokenAction $issueApiToken,
    ) {}

    public function execute(RefreshTokenData $data): TokenPair
    {
        $hash = hash('sha256', $data->refreshToken);
        $token = RefreshToken::where('token_hash', $hash)->first();

        if ($token === null) {
            throw new InvalidTokenException('This refresh token is not recognised.');
        }

        if ($token->isUsed()) {
            $this->transaction(fn () => $this->revokeDeviceFamily($token));

            throw new TokenReuseDetectedException('This refresh token has already been used.');
        }

        if ($token->isExpired()) {
            throw new InvalidTokenException('This refresh token has expired.');
        }

        return $this->transaction(function () use ($token, $data): TokenPair {
            // `refresh_tokens.access_token_id` cascades on delete, so a
            // RefreshToken row can never outlive its access token.
            $accessToken = $token->accessToken;
            $user = User::findOrFail($token->user_id);

            $accessToken->forceFill(['revoked_at' => Carbon::now()])->save();
            $token->forceFill(['used_at' => Carbon::now()])->save();

            $pair = $this->issueApiToken->execute(new IssueTokenData(
                userId: $user->id,
                device: new DeviceData(
                    name: $accessToken->name,
                    id: $token->device_id,
                    platform: $accessToken->device_platform,
                    model: $accessToken->device_model,
                    appVersion: $accessToken->app_version,
                ),
                abilities: $accessToken->abilities ?? ['*'],
                schoolId: $accessToken->school_id,
                ip: $data->ip,
            ));

            $newToken = RefreshToken::where('token_hash', hash('sha256', $pair->refreshToken))->first();
            $token->forceFill(['replaced_by_id' => $newToken?->id])->save();

            return $pair;
        });
    }

    private function revokeDeviceFamily(RefreshToken $token): void
    {
        $user = User::findOrFail($token->user_id);

        $accessTokenIds = RefreshToken::where('user_id', $user->id)
            ->where('device_id', $token->device_id)
            ->pluck('access_token_id');

        PersonalAccessToken::whereIn('id', $accessTokenIds)->update(['revoked_at' => Carbon::now()]);
        RefreshToken::where('user_id', $user->id)->where('device_id', $token->device_id)->update(['used_at' => Carbon::now()]);

        event(new RefreshTokenReuseDetected($user, $token->device_id));
    }
}
