<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\CreateContractorWorkerData;
use Modules\Security\Models\ContractorWorker;

/**
 * ACT-CreateContractorWorker (Book H2 OPS-06 §2 ⭐/BR-OPS-06-002).
 * `is_cleared` is derived here from what's actually on file — a
 * worker with no recorded police clearance is never cleared,
 * regardless of what a caller might otherwise claim.
 */
final class CreateContractorWorkerAction extends Action
{
    public function execute(CreateContractorWorkerData $data): ContractorWorker
    {
        $isCleared = $data->inductionCompletedOn !== null && $data->policeClearanceOn !== null;

        return $this->transaction(fn (): ContractorWorker => ContractorWorker::create([
            'school_id' => $data->schoolId,
            'contractor_id' => $data->contractorId,
            'full_name' => $data->fullName,
            'id_number' => $data->idNumber,
            'induction_completed_on' => $data->inductionCompletedOn?->toDateString(),
            'police_clearance_on' => $data->policeClearanceOn?->toDateString(),
            'is_cleared' => $isCleared,
            'badge_number' => $data->badgeNumber,
        ]));
    }
}
