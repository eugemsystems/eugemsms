<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\AnalyseExaminationSessionData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AnalyseExaminationSession (Book E ACA-07 §5, `Academic\Exams\Analysis`,
 * `exams.results.view`). A read-only report over marks already settled by
 * `ProcessExaminationResultsAction` — never a second result system
 * (BR-ACA-07-016's own wording); this only summarises what exists.
 *
 * Per subject, a candidate's combined percent is the weight-normalised
 * average of their settled percent across that subject's papers IN THIS
 * SESSION (weights renormalised over the papers the candidate actually
 * sat, so a candidate absent from one paper of several isn't penalised
 * by missing weight). This is deliberately session-scoped and distinct
 * from `ACA-05`'s own term-level subject aggregation, which this does
 * not duplicate or feed.
 */
final class AnalyseExaminationSessionAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{
     *     session: array{id: int, name: string, academic_year_id: int},
     *     distributions: array<int, array{subject_id: int, subject_name: string, candidate_count: int, bands: array<string, int>}>,
     *     subject_comparison: array<int, array{subject_id: int, subject_name: string, average_percent: float, median_percent: float, pass_rate_percent: float|null}>,
     *     year_on_year: array<int, array{subject_id: int, subject_name: string, years: array<int, array{academic_year_id: int, average_percent: float}>}>,
     * }
     */
    public function execute(AnalyseExaminationSessionData $data): array
    {
        $session = ExaminationSession::findOrFail($data->sessionId);

        $papers = ExaminationPaper::query()->where('session_id', $session->id)->get();
        $marksByPaper = ExaminationMark::query()
            ->whereIn('paper_id', $papers->pluck('id'))
            ->whereIn('status', ['final', 'moderated'])
            ->get()
            ->groupBy('paper_id');

        $subjects = Subject::query()->whereIn('id', $papers->pluck('subject_id')->unique())->get()->keyBy('id');

        $distributions = [];
        $subjectComparison = [];

        foreach ($papers->groupBy('subject_id') as $subjectId => $subjectPapers) {
            $subjectId = (int) $subjectId;
            $subject = $subjects->get($subjectId);
            $subjectName = $subject === null ? '' : $subject->name;
            $combinedPercents = $this->combinedPercentsBySubject($subjectPapers, $marksByPaper);

            if ($combinedPercents->isEmpty()) {
                continue;
            }

            $scale = $subject !== null && $subject->grading_scale_id !== null ? GradingScale::with('bands')->find($subject->grading_scale_id) : null;
            $passThreshold = $this->passThreshold($scale);

            $bandCounts = [];
            foreach ($combinedPercents as $percent) {
                $band = $scale?->bandFor($percent);
                $label = $band === null ? 'Ungraded' : $band->grade;
                $bandCounts[$label] = ($bandCounts[$label] ?? 0) + 1;
            }

            $distributions[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'candidate_count' => $combinedPercents->count(),
                'bands' => $bandCounts,
            ];

            $subjectComparison[] = [
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'average_percent' => round($combinedPercents->avg(), 2),
                'median_percent' => round($this->median($combinedPercents), 2),
                'pass_rate_percent' => $passThreshold === null ? null : round($combinedPercents->filter(fn (float $p): bool => $p >= $passThreshold)->count() / $combinedPercents->count() * 100, 2),
            ];
        }

        return [
            'session' => ['id' => $session->id, 'name' => $session->name, 'academic_year_id' => $session->academic_year_id],
            'distributions' => $distributions,
            'subject_comparison' => $subjectComparison,
            'year_on_year' => $this->yearOnYear($session, $papers->pluck('subject_id')->unique()->values()),
        ];
    }

    /**
     * @param  EloquentCollection<int, ExaminationPaper>  $subjectPapers
     * @param  Collection<int|string, EloquentCollection<int, ExaminationMark>>  $marksByPaper
     * @return Collection<int, float> keyed by student_id
     */
    private function combinedPercentsBySubject(EloquentCollection $subjectPapers, Collection $marksByPaper): Collection
    {
        $byStudent = [];

        foreach ($subjectPapers as $paper) {
            $weight = (float) $paper->weight_percent;

            foreach (($marksByPaper->get($paper->id) ?? collect()) as $mark) {
                if ($mark->is_absent) {
                    continue;
                }

                $settledMark = $mark->moderated_mark ?? $mark->raw_mark;

                if ($settledMark === null) {
                    continue;
                }

                $percent = ((float) $settledMark / (float) $paper->max_mark) * 100;

                $byStudent[$mark->student_id]['weighted_sum'] = ($byStudent[$mark->student_id]['weighted_sum'] ?? 0.0) + ($percent * $weight);
                $byStudent[$mark->student_id]['weight_total'] = ($byStudent[$mark->student_id]['weight_total'] ?? 0.0) + $weight;
            }
        }

        return collect($byStudent)
            ->filter(fn (array $row): bool => $row['weight_total'] > 0.0)
            ->map(fn (array $row): float => $row['weighted_sum'] / $row['weight_total']);
    }

    /**
     * @param  Collection<int, float>  $percents
     */
    private function median(Collection $percents): float
    {
        $sorted = $percents->values()->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 0 ? (($sorted[$middle - 1] + $sorted[$middle]) / 2) : $sorted[$middle];
    }

    private function passThreshold(?GradingScale $scale): ?float
    {
        if ($scale === null || $scale->pass_grade === null) {
            return null;
        }

        $passBand = $scale->bands->firstWhere('grade', $scale->pass_grade);

        return $passBand === null ? null : (float) $passBand->min_percent;
    }

    /**
     * @param  Collection<int, int>  $subjectIds
     * @return array<int, array{subject_id: int, subject_name: string, years: array<int, array{academic_year_id: int, average_percent: float}>}>
     */
    private function yearOnYear(ExaminationSession $session, Collection $subjectIds): array
    {
        $pastSessions = ExaminationSession::query()
            ->where('school_id', $session->school_id)
            ->where('exam_type', $session->exam_type)
            ->orderBy('academic_year_id')
            ->get();

        $rows = [];

        foreach ($subjectIds as $subjectId) {
            $subject = Subject::find($subjectId);
            $years = [];

            foreach ($pastSessions->groupBy('academic_year_id') as $academicYearId => $sessionsInYear) {
                $papersForSubject = ExaminationPaper::query()
                    ->whereIn('session_id', $sessionsInYear->pluck('id'))
                    ->where('subject_id', $subjectId)
                    ->get();

                if ($papersForSubject->isEmpty()) {
                    continue;
                }

                $marksByPaper = ExaminationMark::query()
                    ->whereIn('paper_id', $papersForSubject->pluck('id'))
                    ->whereIn('status', ['final', 'moderated'])
                    ->get()
                    ->groupBy('paper_id');

                $combinedPercents = $this->combinedPercentsBySubject($papersForSubject, $marksByPaper);

                if ($combinedPercents->isEmpty()) {
                    continue;
                }

                $years[] = ['academic_year_id' => (int) $academicYearId, 'average_percent' => round($combinedPercents->avg(), 2)];
            }

            $rows[] = ['subject_id' => $subjectId, 'subject_name' => $subject === null ? '' : $subject->name, 'years' => $years];
        }

        return $rows;
    }
}
