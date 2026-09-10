<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\TerminateStaffContractData;
use Modules\People\Domain\Events\ContractTerminated;
use Modules\People\Models\StaffContract;

/**
 * ACT-TerminateStaffContract (Book C PPL-04 §4). Full exit processing
 * (clearance checklist, account deactivation, BR-PPL-04-021/022) is
 * `PPL-04`'s own B13 scope and is deferred — this Action only closes
 * the contract itself.
 */
final class TerminateStaffContractAction extends Action
{
    public function execute(TerminateStaffContractData $data): StaffContract
    {
        $contract = StaffContract::findOrFail($data->contractId);

        if ($contract->status !== 'active') {
            throw new InvalidStateTransitionException(
                "Only an active contract can be terminated; this one is [{$contract->status}].",
                ['status' => $contract->status],
            );
        }

        return $this->transaction(function () use ($contract, $data): StaffContract {
            $contract->update([
                'status' => 'terminated',
                'terminated_on' => $data->terminatedOn->toDateString(),
                'termination_reason' => $data->terminationReason,
            ]);

            event(new ContractTerminated($contract));

            return $contract;
        });
    }
}
