<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-PurgeExpiredRecordings (Book I COM-07 §4/BR-COM-07-004,
 * consistent with `CMP-03`'s own retention discipline). Meant to run
 * on a schedule, mirroring `Modules\Comms\Domain\Actions\EscalateUnreadUrgentNoticeAction`'s
 * own "meant to run on a schedule" wiring note.
 */
final class PurgeExpiredRecordingsAction extends Action
{
    public function execute(int $schoolId): int
    {
        return $this->transaction(fn (): int => ScheduledMeeting::where('school_id', $schoolId)
            ->whereNotNull('recording_url')
            ->whereNotNull('recording_expires_on')
            ->where('recording_expires_on', '<', Carbon::today())
            ->update(['recording_url' => null]));
    }
}
