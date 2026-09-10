<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Application;

/**
 * ACT-EnqueueDueDisposals (Book H3 CMP-03 §3 ⭐/BR-CMP-03-006
 * (AC-CMP-03-005)). A record reaching its retention date is queued
 * for REVIEW, never disposed automatically. `review_status` always
 * starts `pending_review` — approval is a separate, later, human step
 * (`ReviewDisposalQueueItemAction`).
 *
 * **Scope boundary**: this pass wires ONE concrete resolver —
 * declined `Application` rows (the spec's own `application_unsuccessful`
 * record class) against `updated_at` (the closest real timestamp to
 * "became unsuccessful", since `Application` has no dedicated
 * `declined_at` column). Wiring every other `record_class` this
 * codebase's other tables could need is real follow-up work, not
 * fabricated here — matches this book's own established "the registry
 * architecture is complete and extensible" boundary.
 */
final class EnqueueDueDisposalsAction extends Action
{
    /**
     * @return Collection<int, DisposalQueueItem>
     */
    public function execute(int $schoolId): Collection
    {
        $schedule = RetentionSchedule::where('school_id', $schoolId)
            ->where('record_class', 'application_unsuccessful')
            ->where('is_active', true)
            ->first();

        if ($schedule === null) {
            return new Collection;
        }

        $cutoff = Carbon::now()->subYears((float) $schedule->retention_years);

        $eligible = Application::where('school_id', $schoolId)
            ->where('status', 'declined')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        return $this->transaction(function () use ($schoolId, $schedule, $eligible): Collection {
            return $eligible->map(fn (Application $application): DisposalQueueItem => DisposalQueueItem::firstOrCreate(
                ['school_id' => $schoolId, 'record_type' => 'application_unsuccessful', 'record_id' => $application->id],
                ['schedule_id' => $schedule->id, 'eligible_on' => Carbon::now()->toDateString(), 'review_status' => 'pending_review'],
            ));
        });
    }
}
