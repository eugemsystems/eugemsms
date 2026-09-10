<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\RefreshToken;

/**
 * ACT-RevokeAllUserTokens (Book A CORE-05 §3/BR-CORE-05-020). Used on
 * deactivation as well as a user-initiated "log out everywhere".
 */
final class RevokeAllUserTokensAction extends Action
{
    public function execute(User $user): int
    {
        return $this->transaction(function () use ($user): int {
            $count = $user->tokens()->whereNull('revoked_at')->update(['revoked_at' => Carbon::now()]);

            RefreshToken::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => Carbon::now()]);

            return $count;
        });
    }
}
