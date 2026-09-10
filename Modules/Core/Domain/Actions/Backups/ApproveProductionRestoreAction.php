<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Backups\ApproveProductionRestoreData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\RestoreTest;

/**
 * ACT-ApproveProductionRestore (Book A CORE-13 §3/BR-CORE-13-009).
 * Second half of the dual-authorisation flow — the approver must be a
 * different user from the one who requested the restore, the same
 * check `ReopenPeriodAction` makes for `period_reopen_requests`.
 * Approval clears this engine's own responsibility; actually running
 * a production restore is an operational runbook step against real
 * infrastructure this build doesn't have, deliberately out of scope
 * here (see `RunRestoreTestAction`'s docblock for the equivalent
 * scope note on the routine weekly restore test).
 */
final class ApproveProductionRestoreAction extends Action
{
    public function execute(ApproveProductionRestoreData $data): RestoreTest
    {
        $restoreTest = RestoreTest::findOrFail($data->restoreTestId);

        if ($restoreTest->status !== 'pending_approval') {
            throw new InvalidStateTransitionException(
                "This restore request is already {$restoreTest->status}.",
                ['restore_test_id' => $restoreTest->id],
            );
        }

        if ($data->approvedByUserId === $restoreTest->requested_by) {
            throw new InvalidStateTransitionException(
                'The approver must be a different user from the one who requested the restore.',
                ['restore_test_id' => $restoreTest->id],
            );
        }

        return $this->transaction(function () use ($restoreTest, $data): RestoreTest {
            $restoreTest->update([
                'status' => 'approved',
                'approved_by' => $data->approvedByUserId,
            ]);

            return $restoreTest;
        });
    }
}
