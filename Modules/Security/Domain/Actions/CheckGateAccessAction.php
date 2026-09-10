<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Models\ContractorWorker;

/**
 * ACT-CheckGateAccess (Book H2 OPS-06 §4 ⭐/BR-OPS-06-001/002/
 * AC-OPS-06-004). Read-only — returns the specific refusal reason or
 * `null` when access is permitted. Absence of a police clearance on
 * file blocks access outright, working near children being the
 * default assumption for any contractor worker on campus, not an
 * opt-in check.
 */
final class CheckGateAccessAction extends Action
{
    public function execute(int $contractorWorkerId): ?string
    {
        $worker = ContractorWorker::with('contractor')->findOrFail($contractorWorkerId);
        $contractor = $worker->contractor;

        if (! $contractor->hasSiteAccessRequirements()) {
            return "Contractor {$contractor->company_name} is not approved, or its insurance/induction is not current (BR-OPS-06-001).";
        }

        if ($worker->police_clearance_on === null) {
            return "Worker {$worker->full_name} has no police clearance on file — required to work unsupervised on a campus with children (BR-OPS-06-002).";
        }

        if (! $worker->is_cleared) {
            return "Worker {$worker->full_name} is not marked cleared for site access.";
        }

        return null;
    }
}
