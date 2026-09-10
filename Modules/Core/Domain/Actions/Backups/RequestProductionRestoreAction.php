<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Backups;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Backups\RequestProductionRestoreData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Backup;
use Modules\Core\Models\RestoreTest;

/**
 * ACT-RequestProductionRestore (Book A CORE-13 §3/BR-CORE-13-009).
 * First half of the dual-authorisation flow for restoring a backup
 * into production — a materially more dangerous operation than the
 * routine, unattended weekly `RunRestoreTestAction`, and the only
 * `restore_tests` row that starts life needing a second person's
 * sign-off before anything runs. Mirrors CORE-03's
 * `RequestPeriodReopenAction`/`ReopenPeriodAction` two-step shape.
 */
final class RequestProductionRestoreAction extends Action
{
    public function execute(RequestProductionRestoreData $data): RestoreTest
    {
        $backup = Backup::findOrFail($data->backupId);

        if (! in_array($backup->status, ['completed', 'verified'], true)) {
            throw new InvalidStateTransitionException(
                "Only a completed backup can be restored — this one is {$backup->status}.",
                ['backup_id' => $backup->id],
            );
        }

        return $this->transaction(fn (): RestoreTest => RestoreTest::create([
            'backup_id' => $backup->id,
            'status' => 'pending_approval',
            'target_environment' => 'production',
            'checks_performed' => ['reason' => $data->reason],
            'requested_by' => $data->requestedByUserId,
        ]));
    }
}
