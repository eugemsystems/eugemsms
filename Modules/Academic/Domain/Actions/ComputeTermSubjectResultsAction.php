<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\ComputeTermSubjectResultsData;
use Modules\Academic\Domain\Support\ContinuousAssessmentProvider;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\AcademicYear;
use Modules\People\Models\Student;

/**
 * ACT-ComputeTermSubjectResults (Book D ACA-05 §3 ⭐, steps 1-5/7).
 * Only `published` assessment marks feed the mean (step 1) — a
 * `submitted`-but-not-yet-`published` mark is visible to staff but
 * does not yet count. An absent-with-reason mark is excluded from
 * both the numerator and denominator of its category's weighted mean
 * (BR-ACA-05-008) — never treated as a zero.
 *
 * The SBP/continuous-assessment contribution (step 2's third branch)
 * now reads `ContinuousAssessmentProvider::outcomeFor()` (Book E
 * ACA-06) — the only sanctioned route to that figure. Only a
 * *verified* outcome counts (BR-ACA-06-014); a merely marked or
 * moderated project is ignored here exactly as if none existed,
 * consistent with `outstandingFor()`'s finalisation gate. The spec
 * does not define how the continuous weight interacts with
 * `Subject::coursework_weight_percent`'s existing two-way split, so
 * this action takes the continuous instrument's own
 * `default_weight_percent` as the continuous share and applies the
 * subject's `coursework_weight_percent` to split what remains between
 * coursework and examination — documented here since the spec is
 * silent on the exact three-way interaction.
 *
 * Class/level positions are NOT written here — they require every
 * other student's result for the same subject/term, computed in one
 * pass by `RecomputeSubjectPositionsAction`.
 */
final class ComputeTermSubjectResultsAction extends Action
{
    public function __construct(
        private readonly ContinuousAssessmentProvider $continuousAssessment,
    ) {}

    public function execute(ComputeTermSubjectResultsData $data): TermSubjectResult
    {
        $student = Student::findOrFail($data->studentId);
        $subject = Subject::findOrFail($data->subjectId);
        $year = AcademicYear::findOrFail($data->academicYearId);

        $marks = AssessmentMark::query()
            ->where('student_id', $data->studentId)
            ->where('term_id', $data->termId)
            ->whereHas('assessment', fn ($q) => $q->where('subject_id', $data->subjectId)->where('status', 'published'))
            ->with('assessment.assessmentType')
            ->get();

        $coursework = $this->weightedMean($marks->filter(
            fn (AssessmentMark $m): bool => $m->assessment->assessmentType->category === 'coursework',
        ));
        $examination = $this->weightedMean($marks->filter(
            fn (AssessmentMark $m): bool => $m->assessment->assessmentType->category === 'examination',
        ));

        $continuousOutcome = $this->continuousAssessment->outcomeFor($student, $subject, $year);
        $continuous = $continuousOutcome !== null && $continuousOutcome->isVerified ? $continuousOutcome->percent : null;
        $continuousWeightPercent = null;

        if ($continuous !== null) {
            $instrument = $this->continuousAssessment->activeInstrument($year);
            $continuousWeightPercent = $instrument === null ? 30.0 : (float) ($instrument->default_weight_percent ?? 30.0);
        }

        $finalPercent = $this->combine($coursework, $examination, $continuous, $continuousWeightPercent, $subject->coursework_weight_percent);

        $scale = $this->resolveScale($subject, $student);
        $band = $finalPercent !== null ? $scale?->bandFor($finalPercent) : null;

        return $this->transaction(fn (): TermSubjectResult => TermSubjectResult::updateOrCreate(
            [
                'school_id' => $student->school_id,
                'term_id' => $data->termId,
                'student_id' => $student->id,
                'subject_id' => $subject->id,
            ],
            [
                'academic_year_id' => $data->academicYearId,
                'coursework_percent' => $coursework,
                'examination_percent' => $examination,
                'continuous_percent' => $continuous,
                'final_percent' => $finalPercent,
                'grade' => $band?->grade,
                'points' => $band?->points,
                'computed_at' => Carbon::now(),
            ],
        ));
    }

    /**
     * @param  Collection<int, AssessmentMark>  $marks
     */
    private function weightedMean(Collection $marks): ?float
    {
        $counted = $marks->reject(fn (AssessmentMark $m): bool => $m->is_absent || $m->percent === null);

        if ($counted->isEmpty()) {
            return null;
        }

        $totalWeight = (float) $counted->sum(fn (AssessmentMark $m): float => (float) $m->assessment->weight_percent);

        if ($totalWeight <= 0.0) {
            return null;
        }

        $weightedSum = (float) $counted->sum(
            fn (AssessmentMark $m): float => (float) $m->percent * (float) $m->assessment->weight_percent,
        );

        return round($weightedSum / $totalWeight, 2);
    }

    private function combine(?float $coursework, ?float $examination, ?float $continuous, ?float $continuousWeightPercent, ?string $courseworkWeightPercent): ?float
    {
        if ($continuous !== null) {
            $continuousWeight = ($continuousWeightPercent ?? 30.0) / 100;
            $remainder = 1 - $continuousWeight;

            if ($coursework !== null && $examination !== null && $courseworkWeightPercent !== null) {
                $w = ((float) $courseworkWeightPercent) / 100;

                return round(
                    $continuous * $continuousWeight
                    + $coursework * $w * $remainder
                    + $examination * (1 - $w) * $remainder,
                    2,
                );
            }

            $academic = $coursework ?? $examination;

            return $academic !== null
                ? round($continuous * $continuousWeight + $academic * $remainder, 2)
                : $continuous;
        }

        if ($coursework !== null && $examination !== null && $courseworkWeightPercent !== null) {
            $w = ((float) $courseworkWeightPercent) / 100;

            return round($coursework * $w + $examination * (1 - $w), 2);
        }

        return $coursework ?? $examination;
    }

    private function resolveScale(Subject $subject, Student $student): ?GradingScale
    {
        if ($subject->grading_scale_id !== null) {
            return GradingScale::find($subject->grading_scale_id);
        }

        return GradingScale::where('school_id', $subject->school_id)
            ->where('is_active', true)
            ->get()
            ->first(fn (GradingScale $scale): bool => in_array($student->grade_level_id, $scale->is_default_for_level ?? [], true));
    }
}
