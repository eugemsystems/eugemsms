<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\DataObjects\MarkAssignmentSubmissionData;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-MarkAssignmentSubmission (Book K ACA-08 §4/BR-ACA-08-005/008 ⭐).
 * `final_mark = raw_mark × (1 − penalty_applied_percent)`, computed
 * once here and never recalculated later even if the assignment's late
 * policy changes afterward (BR-ACA-08-005). When the assignment feeds
 * the gradebook (`assessment_id` set), the FINAL mark is written
 * through `EnterMarkAction` — the same mark-entry path `ACA-05`
 * already validates, not a parallel table (BR-ACA-08-008/
 * AC-ACA-08-004).
 */
final class MarkAssignmentSubmissionAction extends Action
{
    public function __construct(
        private readonly EnterMarkAction $enterMark,
    ) {}

    public function execute(MarkAssignmentSubmissionData $data): AssignmentSubmission
    {
        $submission = AssignmentSubmission::findOrFail($data->submissionId);
        $assignment = Assignment::findOrFail($submission->assignment_id);

        if ($assignment->max_mark !== null && ($data->rawMark < 0 || $data->rawMark > (float) $assignment->max_mark)) {
            throw MarkOutOfRangeException::forMark($data->rawMark, (float) $assignment->max_mark);
        }

        $penaltyPercent = $this->penaltyPercentFor($assignment, $submission);
        $finalMark = round($data->rawMark * (1 - $penaltyPercent / 100), 2);

        return $this->transaction(function () use ($assignment, $submission, $data, $penaltyPercent, $finalMark): AssignmentSubmission {
            $submission->update([
                'raw_mark' => $data->rawMark,
                'penalty_applied_percent' => $penaltyPercent,
                'final_mark' => $finalMark,
                'feedback' => $data->feedback,
                'marked_by' => $data->markedByUserId,
                'marked_at' => Carbon::now(),
                'status' => 'marked',
            ]);

            if ($assignment->assessment_id !== null) {
                $this->enterMark->execute(new EnterMarkData(
                    assessmentId: $assignment->assessment_id,
                    studentId: $submission->student_id,
                    enteredByUserId: $data->markedByUserId,
                    rawMark: $finalMark,
                ));
            }

            return $submission->fresh();
        });
    }

    private function penaltyPercentFor(Assignment $assignment, AssignmentSubmission $submission): float
    {
        if (! $submission->is_late || $assignment->late_policy !== 'accept_penalised') {
            return 0.0;
        }

        $daysLate = (int) ceil(($submission->minutes_late ?? 0) / 1440);
        $penalty = $daysLate * (float) $assignment->late_penalty_percent_per_day;

        return min(100.0, $penalty);
    }
}
