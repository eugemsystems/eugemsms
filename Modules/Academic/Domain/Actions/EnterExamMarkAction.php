<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\EnterExamMarkData;
use Modules\Academic\Domain\Events\MarkVarianceDetected;
use Modules\Academic\Domain\Exceptions\MarkerCannotDoubleAsSecondMarkerException;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-EnterExamMark (Book E ACA-07 §4/BR-ACA-07-013/015/AC-ACA-07-005).
 * When `exams.double_marking_enabled` is off, the first (and only)
 * mark settles the candidate directly. When it is on, the second
 * marker's own mark is never compared against the first until both
 * are in — this action never returns the first marker's value to the
 * caller, and refuses the same staff member entering both.
 */
final class EnterExamMarkAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(EnterExamMarkData $data): ExaminationMark
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);
        $candidate = ExaminationCandidate::findOrFail($data->candidateId);

        $mark = ExaminationMark::firstOrCreate(
            ['school_id' => $paper->school_id, 'paper_id' => $paper->id, 'candidate_id' => $candidate->id],
            ['student_id' => $candidate->student_id, 'is_absent' => false, 'status' => 'pending', 'version' => 1],
        );

        if (in_array($mark->status, ['final', 'moderated'], true)) {
            throw new InvalidStateTransitionException(
                "Candidate #{$candidate->id}'s mark for paper #{$paper->id} is already {$mark->status}.",
                ['mark_id' => $mark->id, 'status' => $mark->status],
            );
        }

        if ($data->isAbsent) {
            return $this->transaction(fn (): ExaminationMark => $this->save($mark, [
                'is_absent' => true, 'raw_mark' => null, 'percent' => null, 'status' => 'final',
                'first_marker_id' => $data->markerStaffId,
            ]));
        }

        $doubleMarking = (bool) $this->settings->get('exams.double_marking_enabled', new ScopeChain(schoolId: $paper->school_id));

        if (! $doubleMarking) {
            $percent = round(($data->mark / (float) $paper->max_mark) * 100, 2);

            return $this->transaction(fn (): ExaminationMark => $this->save($mark, [
                'first_marker_id' => $data->markerStaffId, 'first_mark' => $data->mark,
                'raw_mark' => $data->mark, 'percent' => $percent, 'status' => 'final',
            ]));
        }

        if ($mark->first_marker_id === null) {
            return $this->transaction(fn (): ExaminationMark => $this->save($mark, [
                'first_marker_id' => $data->markerStaffId, 'first_mark' => $data->mark, 'status' => 'first_marked',
            ]));
        }

        if ($mark->second_marker_id !== null) {
            throw new InvalidStateTransitionException(
                "Candidate #{$candidate->id}'s mark for paper #{$paper->id} already has both markers.",
                ['mark_id' => $mark->id],
            );
        }

        if ($data->markerStaffId === $mark->first_marker_id) {
            throw MarkerCannotDoubleAsSecondMarkerException::forCandidate($candidate->id);
        }

        $variance = round(abs((float) $mark->first_mark - $data->mark), 2);
        $threshold = (float) $this->settings->get('exams.double_marking_variance_threshold', new ScopeChain(schoolId: $paper->school_id));

        return $this->transaction(function () use ($mark, $data, $paper, $variance, $threshold): ExaminationMark {
            if ($variance > $threshold) {
                $this->save($mark, [
                    'second_marker_id' => $data->markerStaffId, 'second_mark' => $data->mark,
                    'mark_variance' => $variance, 'status' => 'variance_review',
                ]);

                event(new MarkVarianceDetected($mark));

                return $mark;
            }

            $settled = round(((float) $mark->first_mark + $data->mark) / 2, 2);
            $percent = round(($settled / (float) $paper->max_mark) * 100, 2);

            return $this->save($mark, [
                'second_marker_id' => $data->markerStaffId, 'second_mark' => $data->mark,
                'mark_variance' => $variance, 'raw_mark' => $settled, 'percent' => $percent, 'status' => 'final',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function save(ExaminationMark $mark, array $attributes): ExaminationMark
    {
        $mark->update([...$attributes, 'version' => $mark->version + 1]);

        return $mark;
    }
}
