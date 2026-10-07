<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\DataObjects\ComputeTermResultsData;
use Modules\Academic\Domain\DataObjects\ComputeTermSubjectResultsData;
use Modules\Academic\Domain\DataObjects\RecomputeSubjectPositionsData;
use Modules\Academic\Domain\DataObjects\RecomputeTermPositionsData;
use Modules\Academic\Domain\Events\MarkAmended;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\AssessmentMarkVersion;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ApplyMarkAmendment (Book D ACA-05 §4 ⭐/BR-ACA-05-009/010/011).
 * The actual write every mark amendment ends in — writes the
 * append-only version row, updates the current value, then cascades
 * the recompute across the whole class and level (BR-ACA-05-011) and,
 * if the assessment was already published, regenerates every affected
 * report card. Has no opinion on *whether* this amendment was allowed
 * to happen — that gate lives one layer up, in whichever caller
 * reaches this: `AmendMarkAction` for a not-yet-published assessment,
 * or `MarkAmendmentRequest::onApproved()` for a published one once
 * Core's CORE-07 approval has actually completed. Never call this
 * directly from a screen or controller — it trusts its caller
 * entirely, by design, the same way `DiscountAward::onApproved()`
 * trusts the approval engine rather than re-checking anything itself.
 */
final class ApplyMarkAmendmentAction extends Action
{
    public function __construct(
        private readonly ComputeTermSubjectResultsAction $computeSubjectResults,
        private readonly RecomputeSubjectPositionsAction $recomputeSubjectPositions,
        private readonly ComputeTermResultsAction $computeTermResults,
        private readonly RecomputeTermPositionsAction $recomputeTermPositions,
        private readonly RegenerateReportCardAction $regenerateReportCard,
    ) {}

    public function execute(AmendMarkData $data): AssessmentMarkVersion
    {
        $assessment = Assessment::findOrFail($data->assessmentId);
        $mark = AssessmentMark::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $data->studentId)
            ->firstOrFail();

        if (in_array($assessment->status, ['draft', 'open'], true)) {
            throw new InvalidStateTransitionException(
                'An assessment still draft/open is amended via EnterMarkAction, not AmendMarkAction.',
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        if (mb_strlen($data->changeReason) < 15) {
            throw new InvalidArgumentException('An amendment reason must be at least 15 characters.');
        }

        $wasPublished = $assessment->status === 'published';

        if (! $data->isAbsent && $data->rawMark === null) {
            throw new InvalidArgumentException('A raw mark is required unless the learner is marked absent.');
        }

        $maxMark = (float) $assessment->max_mark;

        if (! $data->isAbsent && ($data->rawMark < 0 || $data->rawMark > $maxMark)) {
            throw MarkOutOfRangeException::forMark($data->rawMark, $maxMark);
        }

        $percent = ! $data->isAbsent ? round(($data->rawMark / $maxMark) * 100, 2) : null;
        $band = $percent !== null ? $this->resolveScale($assessment)?->bandFor($percent) : null;
        $newVersion = $mark->version + 1;

        // The version row snapshots the mark's value AS IT STOOD before
        // this amendment (version = the OLD version number) — the
        // history is a log of every superseded value, never the new
        // one, so "the original entry is never overwritten in history"
        // (BR-ACA-05-009) holds from the very first amendment onward.
        $version = $this->transaction(function () use ($assessment, $mark, $data, $percent, $band, $newVersion, $wasPublished): AssessmentMarkVersion {
            $version = AssessmentMarkVersion::create([
                'school_id' => $assessment->school_id,
                'assessment_id' => $assessment->id,
                'student_id' => $data->studentId,
                'version' => $mark->version,
                'raw_mark' => $mark->raw_mark,
                'percent' => $mark->percent,
                'grade' => $mark->grade,
                'change_reason' => $data->changeReason,
                'was_published' => $wasPublished,
                'changed_by' => $data->changedByUserId,
                'changed_at' => Carbon::now(),
            ]);

            $mark->update([
                'raw_mark' => $data->isAbsent ? null : $data->rawMark,
                'percent' => $percent,
                'grade' => $band?->grade,
                'points' => $band?->points,
                'is_absent' => $data->isAbsent,
                'version' => $newVersion,
                'entered_by' => $data->changedByUserId,
                'entered_at' => Carbon::now(),
            ]);

            event(new MarkAmended($version));

            return $version;
        });

        $before = $this->publishedPositions($assessment->term_id, $data->studentId);

        $this->cascadeRecompute($assessment, $data->studentId);

        if ($wasPublished) {
            $this->regenerateAffectedReportCards($assessment->term_id, $data->studentId, $data->changedByUserId, $before);
        }

        return $version;
    }

    /**
     * Class and level positions of every published report card in the
     * amended learner's class, so the cards that move can be found afterwards.
     *
     * @return array<int, array{class: int|null, level: int|null}>
     */
    private function publishedPositions(int $termId, int $studentId): array
    {
        $classId = TermResult::query()->where('term_id', $termId)->where('student_id', $studentId)->value('class_id');

        if ($classId === null) {
            return [];
        }

        return TermResult::query()->where('term_id', $termId)->where('class_id', $classId)->where('status', 'published')
            ->get(['student_id', 'class_position', 'level_position'])
            ->mapWithKeys(fn (TermResult $r): array => [$r->student_id => ['class' => $r->class_position, 'level' => $r->level_position]])
            ->all();
    }

    /**
     * BR-ACA-05-010/011: the amended learner's card and every published card
     * whose position moved are regenerated, so the set of cards never
     * disagrees with itself.
     *
     * @param  array<int, array{class: int|null, level: int|null}>  $before
     */
    private function regenerateAffectedReportCards(int $termId, int $amendedStudentId, int $userId, array $before): void
    {
        foreach (TermResult::query()->where('term_id', $termId)->whereIn('student_id', array_keys($before))->where('status', 'published')->get() as $result) {
            $moved = ($before[$result->student_id]['class'] ?? null) !== $result->class_position || ($before[$result->student_id]['level'] ?? null) !== $result->level_position;

            if (($result->student_id === $amendedStudentId || $moved) && $result->report_document_id !== null) {
                $this->regenerateReportCard->execute($result, $userId);
            }
        }
    }

    private function cascadeRecompute(Assessment $assessment, int $studentId): void
    {
        $this->computeSubjectResults->execute(new ComputeTermSubjectResultsData(
            studentId: $studentId,
            subjectId: $assessment->subject_id,
            academicYearId: $assessment->academic_year_id,
            termId: $assessment->term_id,
        ));

        $this->recomputeSubjectPositions->execute(new RecomputeSubjectPositionsData(
            schoolId: $assessment->school_id,
            termId: $assessment->term_id,
            subjectId: $assessment->subject_id,
        ));

        $this->computeTermResults->execute(new ComputeTermResultsData(
            studentId: $studentId,
            termId: $assessment->term_id,
        ));

        $classId = ClassAllocation::query()
            ->where('student_id', $studentId)
            ->where('term_id', $assessment->term_id)
            ->where('status', 'confirmed')
            ->value('class_id');

        if ($classId !== null) {
            $this->recomputeTermPositions->execute(new RecomputeTermPositionsData(
                schoolId: $assessment->school_id,
                termId: $assessment->term_id,
                classId: $classId,
            ));
        }
    }

    private function resolveScale(Assessment $assessment): ?GradingScale
    {
        if ($assessment->grading_scale_id !== null) {
            return GradingScale::find($assessment->grading_scale_id);
        }

        $subject = Subject::find($assessment->subject_id);

        return $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id) : null;
    }
}
