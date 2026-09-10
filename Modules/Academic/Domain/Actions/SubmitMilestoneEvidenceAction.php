<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\SubmitMilestoneEvidenceData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\LearnerProjectMilestone;
use Modules\Academic\Models\ProjectEvidence;
use Modules\Academic\Models\ProjectMilestone;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-SubmitMilestoneEvidence (Book E ACA-06 §2/§5/BR-ACA-06-009/010).
 * Evidence submission is open until the milestone deadline; a late
 * submission is always recorded rather than blocked outright — the
 * per-school "permit or block" setting is a later scope note since no
 * settings key exists yet for it.
 */
final class SubmitMilestoneEvidenceAction extends Action
{
    public function execute(SubmitMilestoneEvidenceData $data): ProjectEvidence
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);
        $milestone = ProjectMilestone::findOrFail($data->milestoneId);

        if ($data->fileId === null && $data->externalUrl === null) {
            throw new InvalidArgumentException('Evidence requires either a file or an external URL.');
        }

        return $this->transaction(function () use ($learnerProject, $milestone, $data): ProjectEvidence {
            $now = Carbon::now();
            $isLate = $now->greaterThan($milestone->due_on);

            $evidence = ProjectEvidence::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'milestone_id' => $milestone->id,
                'evidence_type' => $data->evidenceType,
                'file_id' => $data->fileId,
                'external_url' => $data->externalUrl,
                'caption' => $data->caption,
                'uploaded_by' => $data->uploadedBy,
                'uploaded_at' => $now,
                'is_final_submission' => false,
            ]);

            LearnerProjectMilestone::updateOrCreate(
                ['school_id' => $learnerProject->school_id, 'learner_project_id' => $learnerProject->id, 'milestone_id' => $milestone->id],
                ['status' => $isLate ? 'submitted_late' : 'submitted', 'submitted_at' => $now],
            );

            if ($learnerProject->status === 'assigned') {
                $learnerProject->update(['status' => 'in_progress']);
            }

            return $evidence;
        });
    }
}
