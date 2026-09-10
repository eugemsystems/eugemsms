<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Closure;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\RecomputeSubjectPositionsData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Student;

/**
 * ACT-RecomputeSubjectPositions (Book D ACA-05 §3 step 6/BR-ACA-05-003/011).
 * Ranks every `term_subject_results` row for one subject/term together
 * — a position can never be computed for a single learner in
 * isolation. Tie-break is student id ascending (deterministic, not
 * the spec's literal "higher raw total, then alphabetical" — a
 * documented simplification for this pass).
 * `lower_is_better` (BR-ACA-05-003) reverses the sort so the lowest
 * percent/points ranks first.
 */
final class RecomputeSubjectPositionsAction extends Action
{
    public function execute(RecomputeSubjectPositionsData $data): void
    {
        $results = TermSubjectResult::query()
            ->where('school_id', $data->schoolId)
            ->where('term_id', $data->termId)
            ->where('subject_id', $data->subjectId)
            ->whereNotNull('final_percent')
            ->get();

        if ($results->isEmpty()) {
            return;
        }

        $subject = Subject::find($data->subjectId);
        $scale = null;

        if ($subject !== null && $subject->grading_scale_id !== null) {
            $scale = GradingScale::find($subject->grading_scale_id);
        }

        $lowerIsBetter = $scale !== null && $scale->lower_is_better;

        $average = round((float) $results->avg('final_percent'), 2);
        $results->each(fn (TermSubjectResult $r) => $r->update(['subject_average' => $average]));

        $studentIds = $results->pluck('student_id')->all();
        $classByStudent = ClassAllocation::query()
            ->whereIn('student_id', $studentIds)
            ->where('term_id', $data->termId)
            ->where('status', 'confirmed')
            ->pluck('class_id', 'student_id');
        $levelByStudent = Student::withoutGlobalScopes()->whereIn('id', $studentIds)->pluck('grade_level_id', 'id');

        $this->rankBy($results, $lowerIsBetter, fn (TermSubjectResult $r): int => $classByStudent[$r->student_id] ?? 0, 'class_position', 'class_size');
        $this->rankBy($results, $lowerIsBetter, fn (TermSubjectResult $r): int => $levelByStudent[$r->student_id] ?? 0, 'level_position', null);
    }

    /**
     * @param  Collection<int, TermSubjectResult>  $results
     * @param  Closure(TermSubjectResult): int  $groupKey  0 means "no class/level found"
     */
    private function rankBy(Collection $results, bool $lowerIsBetter, Closure $groupKey, string $positionColumn, ?string $sizeColumn): void
    {
        foreach ($results->groupBy($groupKey) as $key => $group) {
            if ((int) $key === 0) {
                continue;
            }

            $ranked = $group->sort(
                fn (TermSubjectResult $a, TermSubjectResult $b): int => $lowerIsBetter
                    ? [(float) $a->final_percent, $a->student_id] <=> [(float) $b->final_percent, $b->student_id]
                    : [(float) $b->final_percent, $a->student_id] <=> [(float) $a->final_percent, $b->student_id],
            )->values();

            foreach ($ranked as $index => $result) {
                $update = [$positionColumn => $index + 1];

                if ($sizeColumn !== null) {
                    $update[$sizeColumn] = $ranked->count();
                }

                $result->update($update);
            }
        }
    }
}
