<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\RecordReturnData;
use Modules\Boarding\Domain\Events\LearnerReturned;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RecordReturn (Book F BRD-03 §4/BR-BRD-03-016). Late minutes are
 * computed here and reported — repeated lateness feeding the
 * pastoral record is `BRD-07`'s own concern (not built), so it is
 * only computed and stored here, not escalated further.
 */
final class RecordReturnAction extends Action
{
    public function execute(RecordReturnData $data): Exeat
    {
        $exeat = Exeat::findOrFail($data->exeatId);

        if (! in_array($exeat->status, ['departed', 'overdue'], true)) {
            throw new InvalidStateTransitionException(
                "Exeat #{$exeat->id} must be departed or overdue to record a return (currently {$exeat->status}).",
                ['exeat_id' => $exeat->id, 'status' => $exeat->status],
            );
        }

        $now = Carbon::now();
        $lateMinutes = $now->greaterThan($exeat->returns_by) ? (int) $exeat->returns_by->diffInMinutes($now) : 0;

        return $this->transaction(function () use ($exeat, $data, $now, $lateMinutes): Exeat {
            $exeat->update([
                'status' => 'returned',
                'actual_return_at' => $now,
                'return_recorded_by' => $data->recordedByUserId,
                'late_return_minutes' => $lateMinutes,
            ]);

            event(new LearnerReturned($exeat));

            return $exeat;
        });
    }
}
