<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\ApproveContractorData;
use Modules\Security\Models\Contractor;

/**
 * ACT-ApproveContractor (Book H2 OPS-06 §4/BR-OPS-06-001). Requires
 * valid insurance and a completed safety induction on file before
 * approval — the same hard preconditions
 * `Contractor::hasSiteAccessRequirements()` checks again at every
 * gate-access decision, since approval today doesn't guarantee
 * insurance is still current tomorrow.
 */
final class ApproveContractorAction extends Action
{
    public function execute(int $contractorId, ApproveContractorData $data): Contractor
    {
        $contractor = Contractor::findOrFail($contractorId);

        if ($data->insuranceExpiresOn->isPast()) {
            throw ValidationException::withMessages([
                'insuranceExpiresOn' => 'Insurance must be current, not already expired, to approve a contractor (BR-OPS-06-001).',
            ]);
        }

        return $this->transaction(fn (): Contractor => tap($contractor)->update([
            'status' => 'approved',
            'insurance_expires_on' => $data->insuranceExpiresOn->toDateString(),
            'insurance_file_id' => $data->insuranceFileId,
            'safety_induction_on' => $data->safetyInductionOn->toDateString(),
            'induction_valid_until' => $data->inductionValidUntil->toDateString(),
            'police_clearance_on' => $data->policeClearanceOn?->toDateString(),
            'approved_by' => $data->approvedByUserId,
        ]));
    }
}
