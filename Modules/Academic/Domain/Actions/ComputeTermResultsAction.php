<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\ComputeTermResultsData;
use Modules\Academic\Domain\DataObjects\RebuildAttendanceSummaryData;
use Modules\Academic\Domain\Events\ResultsComputed;
use Modules\Academic\Domain\Exceptions\NoCurrentClassAllocationException;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\GradeBand;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\RebuildBehaviourPointBalanceAction;

/**
 * ACT-ComputeTermResults (Book D ACA-05 §3 ⭐, steps 8-16). Aggregates
 * across a learner's own `term_subject_results` rows — never
 * recomputes a subject result itself (that's
 * `ComputeTermSubjectResultsAction`'s job, always run first).
 *
 * `attendance_percent` is recomputed from source (BR-ACA-05-013) by
 * calling `RebuildAttendanceSummaryAction` fresh rather than reading
 * the possibly-stale `attendance_summaries` cache row directly.
 * `conduct_grade` (Book G BRD-07/BR-BRD-07-015) is now read from
 * `RebuildBehaviourPointBalanceAction`'s own fresh rebuild rather than
 * a possibly-stale `behaviour_point_balances` row, the same "recompute
 * from source, don't trust the cache" discipline as attendance — null
 * when the school has no behaviour data configured for this term. The
 * `finance.report_gate_enabled` withholding check (BR-ACA-05-014) is
 * deferred — this action always leaves `status` at `computed`, never
 * `withheld`; see this module's own scope note.
 */
final class ComputeTermResultsAction extends Action
{
    public function __construct(
        private readonly RebuildAttendanceSummaryAction $rebuildAttendance,
        private readonly SettingResolver $settings,
        private readonly RebuildBehaviourPointBalanceAction $rebuildBehaviourPointBalance,
    ) {}

    public function execute(ComputeTermResultsData $data): TermResult
    {
        $student = Student::findOrFail($data->studentId);
        $term = Term::findOrFail($data->termId);

        $allocation = ClassAllocation::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->where('status', 'confirmed')
            ->first();

        if ($allocation === null) {
            throw NoCurrentClassAllocationException::forStudent($student->id, $term->id);
        }

        $subjectResults = TermSubjectResult::query()
            ->where('student_id', $student->id)
            ->where('term_id', $term->id)
            ->get();

        $withResult = $subjectResults->filter(fn (TermSubjectResult $r): bool => $r->final_percent !== null);
        $subjectsTaken = $withResult->count();
        $subjectsPassed = $withResult->filter(fn (TermSubjectResult $r): bool => $this->isPass($r))->count();
        $totalMarks = round((float) $withResult->sum(fn (TermSubjectResult $r): float => (float) $r->final_percent), 2);
        $averagePercent = $subjectsTaken > 0 ? round($totalMarks / $subjectsTaken, 2) : null;
        $totalPoints = round((float) $withResult->whereNotNull('points')->sum(fn (TermSubjectResult $r): float => (float) $r->points), 2);
        $aggregate = $this->aggregateForLowerIsBetterScales($withResult);

        $attendanceSummary = $this->rebuildAttendance->execute(new RebuildAttendanceSummaryData(
            studentId: $student->id,
            termId: $term->id,
        ));

        $promotion = $averagePercent !== null
            ? $this->promotionRecommendation($student->school_id, $averagePercent, $subjectsPassed, (float) ($attendanceSummary->attendance_percent ?? 0))
            : null;

        $conductGrade = $this->rebuildBehaviourPointBalance->execute($student->school_id, $student->id, $term->id)->conduct_grade;

        return $this->transaction(function () use ($student, $term, $allocation, $subjectsTaken, $subjectsPassed, $totalMarks, $averagePercent, $totalPoints, $aggregate, $attendanceSummary, $promotion, $conductGrade): TermResult {
            $result = TermResult::updateOrCreate(
                ['school_id' => $student->school_id, 'term_id' => $term->id, 'student_id' => $student->id],
                [
                    'academic_year_id' => $term->academic_year_id,
                    'class_id' => $allocation->class_id,
                    'subjects_taken' => $subjectsTaken,
                    'subjects_passed' => $subjectsPassed,
                    'total_marks' => $totalMarks,
                    'average_percent' => $averagePercent,
                    'total_points' => $totalPoints,
                    'aggregate' => $aggregate,
                    'attendance_percent' => $attendanceSummary->attendance_percent,
                    'conduct_grade' => $conductGrade,
                    'promotion_recommendation' => $promotion,
                    'status' => 'computed',
                ],
            );

            event(new ResultsComputed($result));

            return $result;
        });
    }

    private function isPass(TermSubjectResult $result): bool
    {
        $band = $this->bandFor($result);

        if ($band !== null) {
            return $band->is_pass;
        }

        return $result->final_percent !== null && (float) $result->final_percent >= 50.0;
    }

    /**
     * @param  Collection<int, TermSubjectResult>  $withResult
     */
    private function aggregateForLowerIsBetterScales($withResult): ?int
    {
        $sum = 0.0;
        $any = false;

        foreach ($withResult as $result) {
            $scale = $this->scaleFor($result);

            if ($scale?->lower_is_better === true && $result->points !== null) {
                $sum += (float) $result->points;
                $any = true;
            }
        }

        return $any ? (int) round($sum) : null;
    }

    private function bandFor(TermSubjectResult $result): ?GradeBand
    {
        $scale = $this->scaleFor($result);

        return $scale !== null && $result->final_percent !== null ? $scale->bandFor((float) $result->final_percent) : null;
    }

    private function scaleFor(TermSubjectResult $result): ?GradingScale
    {
        $subject = Subject::find($result->subject_id);

        return $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id) : null;
    }

    private function promotionRecommendation(int $schoolId, float $average, int $subjectsPassed, float $attendance): string
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $minAverage = (float) $this->settings->get('academic.promotion_min_average', $scope);
        $minSubjects = (int) $this->settings->get('academic.promotion_min_subjects_passed', $scope);
        $minAttendance = (float) $this->settings->get('academic.promotion_min_attendance', $scope);

        if ($average >= $minAverage && $subjectsPassed >= $minSubjects && $attendance >= $minAttendance) {
            return 'promote';
        }

        if ($average < $minAverage / 2 || $subjectsPassed === 0) {
            return 'repeat';
        }

        return 'review';
    }
}
