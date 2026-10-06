<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\RevokeAllUserTokensAction;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Guardian;

/**
 * ACT-RevokeGuardianPortalAccess (Book C PPL-03 §6). Takes the parent app away from a guardian: the
 * guardian record is unlinked from the account, the account's membership of this school is
 * deactivated, and every signed-in device is signed out at once. The account itself is kept, since
 * the person may have other roles; it simply can no longer act for this school.
 */
final class RevokeGuardianPortalAccessAction extends Action
{
    public function __construct(
        private readonly RevokeAllUserTokensAction $revokeTokens,
    ) {}

    public function execute(int $guardianId): Guardian
    {
        $guardian = Guardian::query()->findOrFail($guardianId);

        if ($guardian->user_id === null) {
            throw new InvalidStateTransitionException('This guardian has no portal access to withdraw.');
        }

        return $this->transaction(function () use ($guardian): Guardian {
            $user = User::query()->findOrFail($guardian->user_id);

            DB::table('school_user')->where('user_id', $user->id)->where('school_id', $guardian->school_id)->update(['status' => 'inactive']);
            $this->revokeTokens->execute($user);
            $guardian->update(['user_id' => null]);

            return $guardian;
        });
    }
}
