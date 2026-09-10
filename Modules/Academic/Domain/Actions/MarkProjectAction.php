<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\MarkProjectData;
use Modules\Academic\Domain\Exceptions\CriterionMarkExceedsMaximumException;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectBrief;
use Modules\Academic\Models\ProjectMarkVersion;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-MarkProject (Book E ACA-06 §5/§6/BR-ACA-06-007/011). Marks a
 * submitted project criterion by criterion against its rubric. Every
 * write here creates an append-only `project_mark_versions` row
 * (BR-ACA-06-011) before the current-value update, mirroring
 * `AmendMarkAction`'s own transaction ordering.
 */
final class MarkProjectAction extends Action
{
    public function execute(MarkProjectData $data): LearnerProject
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if ($learnerProject->status !== 'submitted') {
            throw new InvalidStateTransitionException(
                "A project must be submitted before it can be marked (currently {$learnerProject->status}).",
                ['learner_project_id' => $learnerProject->id, 'status' => $learnerProject->status],
            );
        }

        $brief = ProjectBrief::findOrFail($learnerProject->brief_id);
        $rubric = ProjectRubric::with('criteria')->findOrFail($brief->rubric_id);
        $criteriaByName = $rubric->criteria->keyBy('criterion');

        $criterionMarks = [];
        $rawMark = 0.0;

        foreach ($data->criterionMarks as $input) {
            $criterion = $criteriaByName->get($input->criterion);

            if ($criterion === null) {
                throw new InvalidArgumentException("Rubric #{$rubric->id} has no criterion \"{$input->criterion}\".");
            }

            if ($input->mark < 0 || $input->mark > (float) $criterion->max_mark) {
                throw CriterionMarkExceedsMaximumException::forCriterion($input->criterion, $input->mark, (float) $criterion->max_mark);
            }

            $criterionMarks[$input->criterion] = $input->mark;
            $rawMark += $input->mark;
        }

        $percent = round(($rawMark / (float) $brief->max_mark) * 100, 2);
        $subject = Subject::find($brief->subject_id);
        $band = $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id)?->bandFor($percent) : null;
        $newVersion = $learnerProject->version + 1;

        return $this->transaction(function () use ($learnerProject, $data, $criterionMarks, $rawMark, $percent, $band, $newVersion): LearnerProject {
            ProjectMarkVersion::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'version' => $newVersion,
                'raw_mark' => $rawMark,
                'criterion_marks' => $criterionMarks,
                'stage' => 'marked',
                'change_reason' => null,
                'changed_by' => $data->markerStaffId,
                'changed_at' => Carbon::now(),
            ]);

            $learnerProject->update([
                'status' => 'marked',
                'raw_mark' => $rawMark,
                'percent' => $percent,
                'grade' => $band?->grade,
                'criterion_marks' => $criterionMarks,
                'marker_staff_id' => $data->markerStaffId,
                'marked_at' => Carbon::now(),
                'marker_comment' => $data->markerComment,
                'version' => $newVersion,
            ]);

            return $learnerProject;
        });
    }
}
