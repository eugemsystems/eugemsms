<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\Events\ContractorSiteAccessRefused;
use Modules\Security\Domain\Exceptions\GateAccessRefusedException;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Security\Models\ContractorWorker;

/**
 * ACT-SignInContractorWorker (Book H2 OPS-06 §4 ⭐/BR-OPS-06-002/
 * AC-OPS-06-004). Refuses outright without `CheckGateAccessAction`'s
 * clearance — the gate is where the rule is actually enforced, not
 * just recorded.
 */
final class SignInContractorWorkerAction extends Action
{
    public function __construct(
        private readonly CheckGateAccessAction $checkGateAccess,
    ) {}

    public function execute(int $contractorWorkerId, int $gateStaffUserId): ContractorSiteVisit
    {
        $worker = ContractorWorker::findOrFail($contractorWorkerId);
        $reason = $this->checkGateAccess->execute($worker->id);

        if ($reason !== null) {
            event(new ContractorSiteAccessRefused($worker, $reason));

            throw GateAccessRefusedException::forReason($worker->id, $reason);
        }

        return $this->transaction(fn (): ContractorSiteVisit => ContractorSiteVisit::create([
            'school_id' => $worker->school_id,
            'contractor_worker_id' => $worker->id,
            'signed_in_at' => Carbon::now(),
            'gate_staff_in' => $gateStaffUserId,
        ]));
    }
}
