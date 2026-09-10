<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreatePolicyData;
use Modules\Compliance\Models\Policy;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreatePolicy (Book H3 CMP-04 §3/BR-CMP-04-001). A new version
 * is a NEW row (`supersedesPolicyId` chains back to the one it
 * replaces) — a version is never edited in place, matching
 * `AcknowledgePolicyAction`'s own doctrine that an acknowledgement of
 * an earlier version must remain meaningful.
 */
final class CreatePolicyAction extends Action
{
    public function execute(CreatePolicyData $data): Policy
    {
        return $this->transaction(function () use ($data): Policy {
            $policy = Policy::create([
                'school_id' => $data->schoolId,
                'code' => $data->code,
                'title' => $data->title,
                'category' => $data->category,
                'version' => $data->version,
                'content' => $data->content,
                'document_file_id' => $data->documentFileId,
                'effective_from' => $data->effectiveFrom,
                'review_due_on' => $data->reviewDueOn,
                'approved_by' => $data->approvedByUserId,
                'board_approved_on' => $data->boardApprovedOn,
                'requires_acknowledgement' => $data->requiresAcknowledgement,
                'acknowledgement_audiences' => $data->acknowledgementAudiences === [] ? null : $data->acknowledgementAudiences,
                'status' => 'active',
                'supersedes_policy_id' => $data->supersedesPolicyId,
            ]);

            if ($data->supersedesPolicyId !== null) {
                Policy::where('id', $data->supersedesPolicyId)->update(['status' => 'superseded']);
            }

            return $policy;
        });
    }
}
