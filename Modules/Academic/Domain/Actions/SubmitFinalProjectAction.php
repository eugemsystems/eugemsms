<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\SubmitFinalProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectEvidence;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SubmitFinalProject (Book E ACA-06 §5/BR-ACA-06-009/010). The
 * final piece of evidence that moves a project from `in_progress` to
 * `submitted`, ready for marking.
 */
final class SubmitFinalProjectAction extends Action
{
    public function execute(SubmitFinalProjectData $data): ProjectEvidence
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if (in_array($learnerProject->status, ['submitted', 'marked', 'moderated', 'verified', 'exempt'], true)) {
            throw new InvalidStateTransitionException(
                "A project already {$learnerProject->status} cannot be re-submitted.",
                ['learner_project_id' => $learnerProject->id, 'status' => $learnerProject->status],
            );
        }

        if ($data->fileId === null && $data->externalUrl === null) {
            throw new InvalidArgumentException('Final submission requires either a file or an external URL.');
        }

        return $this->transaction(function () use ($learnerProject, $data): ProjectEvidence {
            $now = Carbon::now();

            $evidence = ProjectEvidence::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'milestone_id' => null,
                'evidence_type' => $data->evidenceType,
                'file_id' => $data->fileId,
                'external_url' => $data->externalUrl,
                'caption' => $data->caption,
                'uploaded_by' => $data->uploadedBy,
                'uploaded_at' => $now,
                'is_final_submission' => true,
            ]);

            $learnerProject->update(['status' => 'submitted', 'submitted_at' => $now]);

            return $evidence;
        });
    }
}
