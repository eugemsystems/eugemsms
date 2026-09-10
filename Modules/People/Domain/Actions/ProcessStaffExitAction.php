<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Auth\RevokeTokenAction;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\People\Domain\DataObjects\ProcessStaffExitData;
use Modules\People\Domain\Events\StaffExited;
use Modules\People\Models\Staff;

/**
 * ACT-ProcessStaffExit (Book C PPL-04 §4/BR-PPL-04-022, AC-PPL-04-007).
 * Deliberately not wired to a scheduler — "automatically, on their
 * exit date" means called once that date is reached, matching this
 * codebase's established pattern for date-triggered transitions with
 * no job-scheduler integration built yet (see `ExpireOfferAction`).
 * Account deactivation and token revocation run regardless of the
 * exit clearance checklist's own state — only final pay is gated on
 * that (`ReleaseFinalPayAction`).
 */
final class ProcessStaffExitAction extends Action
{
    public function __construct(
        private readonly VacateEstablishmentPostAction $vacatePost,
        private readonly RevokeTokenAction $revokeToken,
    ) {}

    public function execute(ProcessStaffExitData $data): Staff
    {
        $staff = Staff::findOrFail($data->staffId);

        return $this->transaction(function () use ($staff, $data): Staff {
            $staff->update([
                'status' => 'exited',
                'exited_on' => $data->exitedOn->toDateString(),
                'exit_reason' => $data->exitReason,
            ]);

            if ($staff->post_id !== null) {
                $this->vacatePost->execute($staff->post_id);
            }

            if ($staff->user_id !== null) {
                $user = User::find($staff->user_id);

                if ($user !== null) {
                    $user->update(['status' => UserStatus::Inactive]);

                    foreach ($user->tokens()->whereNull('revoked_at')->pluck('id') as $tokenId) {
                        $this->revokeToken->execute(new RevokeTokenData($tokenId, $data->processedByUserId));
                    }
                }
            }

            event(new StaffExited($staff));

            return $staff->fresh();
        });
    }
}
