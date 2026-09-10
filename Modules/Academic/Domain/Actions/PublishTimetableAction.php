<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\PublishTimetableData;
use Modules\Academic\Domain\Events\TimetablePublished;
use Modules\Academic\Domain\Events\TimetableSuperseded;
use Modules\Academic\Domain\Exceptions\TimetableSlotClashException;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\Timetable;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-PublishTimetable (Book E ACA-03 §6/BR-ACA-03-013/015). Refuses
 * while any of the four hard clash levels exist anywhere in the
 * timetable — not just the ones a manual edit happened to touch —
 * naming them exactly like `CreateTimetableSlotAction` does.
 * Publishing supersedes the prior published version for the same
 * term; both remain retrievable by `effective_from`/`effective_to`.
 * Attendance session generation is a separate, explicit follow-up
 * call (`GenerateAttendanceSessionsFromTimetableAction`), not fired
 * automatically here, so a caller controls the generation window.
 */
final class PublishTimetableAction extends Action
{
    public function __construct(private readonly TimetableClashDetector $detector) {}

    public function execute(PublishTimetableData $data): Timetable
    {
        $timetable = Timetable::with('slots')->findOrFail($data->timetableId);

        $clashes = $this->detector->detect($timetable->slots);

        if ($clashes->isNotEmpty()) {
            throw TimetableSlotClashException::forClashes($clashes);
        }

        return $this->transaction(function () use ($timetable, $data): Timetable {
            $previous = Timetable::query()
                ->where('school_id', $timetable->school_id)
                ->where('term_id', $timetable->term_id)
                ->where('id', '!=', $timetable->id)
                ->where('status', 'published')
                ->get();

            foreach ($previous as $priorTimetable) {
                $priorTimetable->update(['status' => 'superseded', 'effective_to' => Carbon::now()->toDateString()]);
                event(new TimetableSuperseded($priorTimetable->fresh()));
            }

            $timetable->update([
                'status' => 'published',
                'published_by' => $data->publishedByUserId,
                'published_at' => Carbon::now(),
                'effective_from' => $timetable->effective_from ?? Carbon::now()->toDateString(),
                'hard_violations' => 0,
            ]);

            $fresh = $timetable->fresh();

            event(new TimetablePublished($fresh));

            return $fresh;
        });
    }
}
