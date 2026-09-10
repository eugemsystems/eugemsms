<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Security\Models\ContractorSiteVisit;

/**
 * ACT-SignOutContractorWorker (Book H2 OPS-06 §4).
 */
final class SignOutContractorWorkerAction extends Action
{
    public function execute(int $visitId, int $gateStaffUserId): ContractorSiteVisit
    {
        $visit = ContractorSiteVisit::findOrFail($visitId);

        if ($visit->signed_out_at !== null) {
            throw new InvalidStateTransitionException(
                "Contractor site visit #{$visit->id} is already signed out.",
                ['visit_id' => $visit->id],
            );
        }

        return $this->transaction(fn (): ContractorSiteVisit => tap($visit)->update([
            'signed_out_at' => now(),
            'gate_staff_out' => $gateStaffUserId,
        ]));
    }
}
