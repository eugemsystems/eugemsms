<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\AmendVerifiedProjectData;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectMarkVersion;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ApplyVerifiedProjectAmendment (Book E ACA-06 §6, mirroring
 * `ApplyMarkAmendmentAction` from Book D ACA-05). The actual write a
 * verified project's amendment ends in — writes the append-only
 * version row, then updates the project's current mark/grade. Has no
 * gate of its own (not even a "must be verified" check — the caller
 * owns that): the only sanctioned caller is
 * `ProjectAmendmentRequest::onApproved()`, once Core's CORE-07
 * approval has actually completed. Never call this directly from a
 * screen or controller.
 */
final class ApplyVerifiedProjectAmendmentAction extends Action
{
    public function execute(AmendVerifiedProjectData $data): ProjectMarkVersion
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if (mb_strlen($data->changeReason) < 15) {
            throw new InvalidArgumentException('An amendment reason must be at least 15 characters.');
        }

        $brief = ProjectBrief::findOrFail($learnerProject->brief_id);
        $criterionMarks = [];
        $rawMark = 0.0;

        foreach ($data->criterionMarks as $input) {
            $criterionMarks[$input->criterion] = $input->mark;
            $rawMark += $input->mark;
        }

        $percent = round(($rawMark / (float) $brief->max_mark) * 100, 2);
        $subject = Subject::find($brief->subject_id);
        $band = $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id)?->bandFor($percent) : null;
        $newVersion = $learnerProject->version + 1;

        return $this->transaction(function () use ($learnerProject, $data, $criterionMarks, $rawMark, $percent, $band, $newVersion): ProjectMarkVersion {
            $version = ProjectMarkVersion::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'version' => $newVersion,
                'raw_mark' => $rawMark,
                'criterion_marks' => $criterionMarks,
                'stage' => 'amended',
                'change_reason' => $data->changeReason,
                'changed_by' => $data->changedByUserId,
                'changed_at' => Carbon::now(),
            ]);

            $learnerProject->update([
                'raw_mark' => $rawMark,
                'percent' => $percent,
                'grade' => $band?->grade,
                'criterion_marks' => $criterionMarks,
                'version' => $newVersion,
            ]);

            return $version;
        });
    }
}
