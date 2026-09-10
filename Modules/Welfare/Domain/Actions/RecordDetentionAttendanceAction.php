<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\Detention;

/**
 * ACT-RecordDetentionAttendance (Book G BRD-07 §2/BR-BRD-07-012). Once
 * marked, the detention leaves `Detention::CURRENTLY_SCHEDULED_STATUSES`,
 * ending its contribution to the `detention` roll status.
 */
final class RecordDetentionAttendanceAction extends Action
{
    public function execute(int $detentionId, bool $attended, ?string $note = null): Detention
    {
        $detention = Detention::findOrFail($detentionId);

        return $this->transaction(fn (): Detention => tap($detention)->update([
            'attended' => $attended,
            'attendance_note' => $note,
            'status' => $attended ? 'attended' : 'missed',
        ]));
    }
}
