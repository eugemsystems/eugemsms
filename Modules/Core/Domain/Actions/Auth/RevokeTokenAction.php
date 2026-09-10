<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\Events\Auth\TokenRevoked;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\RefreshToken;

/**
 * ACT-RevokeToken (Book A CORE-05 §3/BR-CORE-05-011). Effective on the
 * next request, not on a cache expiry — there is no cached
 * authentication state to invalidate here, only the DB row itself.
 */
final class RevokeTokenAction extends Action
{
    public function execute(RevokeTokenData $data): void
    {
        $token = PersonalAccessToken::findOrFail($data->tokenId);

        $this->transaction(function () use ($token, $data): void {
            $token->forceFill([
                'revoked_at' => Carbon::now(),
                'revoked_by' => $data->revokedByUserId,
            ])->save();

            RefreshToken::where('access_token_id', $token->id)->update(['used_at' => Carbon::now()]);

            event(new TokenRevoked($token->id, $data->revokedByUserId));
        });
    }
}
