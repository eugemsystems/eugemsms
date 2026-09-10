<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ModerateProjectData;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectMarkVersion;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ModerateProject (Book E ACA-06 §5/§6/BR-ACA-06-012). A
 * moderated mark supersedes the marker's mark for downstream
 * computation, but the marker's original `raw_mark`/`percent` is
 * never overwritten — it stays readable alongside `moderated_mark`,
 * both visible per the rule's own wording.
 */
final class ModerateProjectAction extends Action
{
    public function execute(ModerateProjectData $data): LearnerProject
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if ($learnerProject->status !== 'marked') {
            throw new InvalidStateTransitionException(
                "A project must be marked before it can be moderated (currently {$learnerProject->status}).",
                ['learner_project_id' => $learnerProject->id, 'status' => $learnerProject->status],
            );
        }

        $brief = ProjectBrief::findOrFail($learnerProject->brief_id);

        if ($data->moderatedMark < 0 || $data->moderatedMark > (float) $brief->max_mark) {
            throw new InvalidArgumentException("Moderated mark must be between 0 and {$brief->max_mark}.");
        }

        $percent = round(($data->moderatedMark / (float) $brief->max_mark) * 100, 2);
        $subject = Subject::find($brief->subject_id);
        $band = $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id)?->bandFor($percent) : null;
        $newVersion = $learnerProject->version + 1;

        return $this->transaction(function () use ($learnerProject, $data, $percent, $band, $newVersion): LearnerProject {
            ProjectMarkVersion::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'version' => $newVersion,
                'raw_mark' => $data->moderatedMark,
                'criterion_marks' => null,
                'stage' => 'moderated',
                'change_reason' => $data->moderationNote,
                'changed_by' => $data->moderatorStaffId,
                'changed_at' => Carbon::now(),
            ]);

            $learnerProject->update([
                'status' => 'moderated',
                'percent' => $percent,
                'grade' => $band?->grade,
                'moderator_staff_id' => $data->moderatorStaffId,
                'moderated_at' => Carbon::now(),
                'moderated_mark' => $data->moderatedMark,
                'moderation_note' => $data->moderationNote,
                'version' => $newVersion,
            ]);

            return $learnerProject;
        });
    }
}
