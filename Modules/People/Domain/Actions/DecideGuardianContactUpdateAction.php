<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\DecideGuardianContactUpdateData;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianContactUpdate;

/**
 * ACT-DecideGuardianContactUpdate (Book C PPL-03 §6/BR-PPL-03-021). Approving
 * applies the requested details to the guardian in the same transaction as the
 * decision; rejecting leaves them untouched. A decided request is final.
 */
final class DecideGuardianContactUpdateAction extends Action
{
    public function execute(DecideGuardianContactUpdateData $data): GuardianContactUpdate
    {
        $update = GuardianContactUpdate::query()->lockForUpdate()->findOrFail($data->updateId);

        if ($update->status !== 'pending') {
            throw new InvalidStateTransitionException("Contact update #{$update->id} is already [{$update->status}].", ['update_id' => $update->id]);
        }

        return $this->transaction(function () use ($update, $data): GuardianContactUpdate {
            if ($data->approve) {
                Guardian::findOrFail($update->guardian_id)->update($update->changes);
            }

            $update->update([
                'status' => $data->approve ? 'approved' : 'rejected',
                'decided_by' => $data->decidedByUserId,
                'decided_at' => Carbon::now(),
                'decision_note' => $data->note,
            ]);

            return $update->fresh();
        });
    }
}
