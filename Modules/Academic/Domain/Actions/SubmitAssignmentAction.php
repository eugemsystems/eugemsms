<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\SubmitAssignmentData;
use Modules\Academic\Domain\Exceptions\AssignmentSubmissionBlockedException;
use Modules\Academic\Domain\Exceptions\LearnerNotEnrolledException;
use Modules\Academic\Domain\Exceptions\ResubmissionNotAllowedException;
use Modules\Academic\Domain\Support\SubmissionSimilarityChecker;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-SubmitAssignment (Book K ACA-08 §4/BR-ACA-08-002/004/006/007).
 * `penalty_applied_percent`/`final_mark` are NOT computed here — only
 * `is_late`/`minutes_late` are recorded at submit time; the actual
 * penalty is computed once, at marking time, by
 * `MarkAssignmentSubmissionAction` (BR-ACA-08-005), so a school
 * changing its late policy after the fact never silently rewrites an
 * already-marked submission's penalty.
 */
final class SubmitAssignmentAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly SubmissionSimilarityChecker $similarityChecker,
    ) {}

    public function execute(SubmitAssignmentData $data): AssignmentSubmission
    {
        $assignment = Assignment::with('courseSpace')->findOrFail($data->assignmentId);

        if ($assignment->status !== 'published') {
            throw new InvalidStateTransitionException(
                "Assignment #{$assignment->id} is not open for submission.",
                ['assignment_id' => $assignment->id, 'status' => $assignment->status],
            );
        }

        if (! TeachingGroupMember::isActiveFor($data->studentId, $assignment->courseSpace->teaching_group_id)) {
            throw LearnerNotEnrolledException::forTeachingGroup($data->studentId, $assignment->courseSpace->teaching_group_id);
        }

        $priorAttempts = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $data->studentId)
            ->count();

        if ($priorAttempts > 0 && ! $assignment->allows_resubmission) {
            throw ResubmissionNotAllowedException::forAssignment($assignment->id, $data->studentId);
        }

        $now = Carbon::now();
        $isLate = $now->greaterThan($assignment->due_at);

        if ($isLate && $assignment->late_policy === 'block') {
            throw AssignmentSubmissionBlockedException::forAssignment($assignment->id);
        }

        return $this->transaction(function () use ($assignment, $data, $now, $isLate, $priorAttempts): AssignmentSubmission {
            $submission = AssignmentSubmission::create([
                'school_id' => $assignment->school_id,
                'assignment_id' => $assignment->id,
                'student_id' => $data->studentId,
                'attempt_number' => $priorAttempts + 1,
                'submitted_text' => $data->submittedText,
                'submitted_link' => $data->submittedLink,
                'file_ids' => $data->fileIds,
                'submitted_at' => $now,
                'is_late' => $isLate,
                'minutes_late' => $isLate ? (int) $assignment->due_at->diffInMinutes($now) : null,
                'similarity_flag' => false,
                'status' => 'submitted',
            ]);

            $this->flagSimilarMatches($assignment, $submission);

            return $submission->fresh();
        });
    }

    private function flagSimilarMatches(Assignment $assignment, AssignmentSubmission $submission): void
    {
        $threshold = (int) $this->settings->get('academic.lms_similarity_threshold_percent', new ScopeChain(schoolId: $assignment->school_id));

        $others = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('id', '!=', $submission->id)
            ->get();

        $matches = $this->similarityChecker->findMatches($submission, $others, $threshold);

        if ($matches === []) {
            return;
        }

        $submission->update(['similarity_flag' => true, 'similarity_matches' => $matches]);

        foreach ($others->whereIn('id', $matches) as $other) {
            $otherMatches = array_unique([...($other->similarity_matches ?? []), $submission->id]);
            $other->update(['similarity_flag' => true, 'similarity_matches' => array_values($otherMatches)]);
        }
    }
}
