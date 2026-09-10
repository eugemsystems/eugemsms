<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\RecomputeTermPositionsData;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\SchoolClass;

/**
 * ACT-RecomputeTermPositions (Book D ACA-05 §3 step 12/BR-ACA-05-011/012).
 * Ranks every learner's `class_id`'s classmates on `average_percent`
 * (`academic.position_basis` — only this basis is implemented in this
 * pass, not the `total_marks`/`points` alternatives the setting also
 * allows), tie-broken by `total_marks` then student id. Also ranks
 * every learner sharing the same grade level as this class, for
 * `level_position` — both spans, per BR-ACA-05-011, never just the
 * one class this call was asked about... except level is genuinely
 * cross-class, so this action must be called once per distinct class
 * to cover every class in the level; `AmendMarkAction` does exactly
 * that.
 */
final class RecomputeTermPositionsAction extends Action
{
    public function execute(RecomputeTermPositionsData $data): void
    {
        $class = SchoolClass::findOrFail($data->classId);

        $classResults = TermResult::query()
            ->where('school_id', $data->schoolId)
            ->where('term_id', $data->termId)
            ->where('class_id', $data->classId)
            ->whereNotNull('average_percent')
            ->get();

        $this->rank($classResults, 'class_position', 'class_size');

        $levelClassIds = SchoolClass::query()
            ->where('school_id', $data->schoolId)
            ->where('grade_level_id', $class->grade_level_id)
            ->pluck('id');

        $levelResults = TermResult::query()
            ->where('school_id', $data->schoolId)
            ->where('term_id', $data->termId)
            ->whereIn('class_id', $levelClassIds)
            ->whereNotNull('average_percent')
            ->get();

        $this->rank($levelResults, 'level_position', 'level_size');
    }

    /**
     * @param  Collection<int, TermResult>  $results
     */
    private function rank($results, string $positionColumn, string $sizeColumn): void
    {
        if ($results->isEmpty()) {
            return;
        }

        $ranked = $results->sort(
            fn (TermResult $a, TermResult $b): int => [(float) $b->average_percent, (float) ($b->total_marks ?? 0), $a->student_id]
                <=> [(float) $a->average_percent, (float) ($a->total_marks ?? 0), $b->student_id],
        )->values();

        foreach ($ranked as $index => $result) {
            $result->update([$positionColumn => $index + 1, $sizeColumn => $ranked->count()]);
        }
    }
}
