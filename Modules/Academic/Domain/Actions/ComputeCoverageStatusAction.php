<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\ComputeCoverageStatusData;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;

/**
 * ACT-ComputeCoverageStatus (Book K ACA-11 §4/BR-ACA-11-002/
 * AC-ACA-11-002). "Week" is the teaching week number counted from the
 * scheme's own term start — never a judgement, just where a topic's
 * planned week sits against either its actual delivery week or, if
 * not yet delivered, the current week.
 */
final class ComputeCoverageStatusAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array<int, array{index: int, topic: mixed, plannedWeek: int, deliveredWeek: int|null, status: string, varianceNote: string|null}>
     */
    public function execute(ComputeCoverageStatusData $data): array
    {
        $scheme = SchemeOfWork::findOrFail($data->schemeOfWorkId);
        $term = Term::findOrFail($scheme->term_id);
        $currentWeek = $this->weekFor(Carbon::today(), $term);

        $records = SyllabusCoverageRecord::query()
            ->where('scheme_of_work_id', $scheme->id)
            ->orderBy('planned_topic_index')
            ->get()
            ->keyBy('planned_topic_index');

        $result = [];

        foreach ($scheme->planned_topics as $index => $planned) {
            $plannedWeek = (int) ($planned['week'] ?? 1);
            $record = $records->get($index);
            $deliveredOn = $record?->actual_delivered_on;

            if ($deliveredOn !== null) {
                $deliveredWeek = $this->weekFor($deliveredOn, $term);
                $status = $deliveredWeek > $plannedWeek ? 'behind' : 'on_track';
            } else {
                $deliveredWeek = null;
                $status = $currentWeek > $plannedWeek ? 'behind' : 'not_yet_due';
            }

            $result[] = [
                'index' => $index,
                'topic' => $planned['topic'] ?? null,
                'plannedWeek' => $plannedWeek,
                'deliveredWeek' => $deliveredWeek,
                'status' => $status,
                'varianceNote' => $record?->variance_note,
            ];
        }

        return $result;
    }

    private function weekFor(CarbonInterface $date, Term $term): int
    {
        $daysSinceStart = max(0, (int) $term->starts_on->diffInDays($date));

        return (int) intdiv($daysSinceStart, 7) + 1;
    }
}
