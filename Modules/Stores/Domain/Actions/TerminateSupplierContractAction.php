<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Models\SupplierContract;

/**
 * ACT-TerminateSupplierContract (Book H1 FIN-08 §6). Ends a contract early; it stops
 * raising expiry alerts. Terminating is final and idempotent-refusing: a contract
 * already terminated cannot be terminated again.
 */
final class TerminateSupplierContractAction extends Action
{
    public function execute(int $contractId): SupplierContract
    {
        return $this->transaction(function () use ($contractId): SupplierContract {
            $contract = SupplierContract::query()->lockForUpdate()->findOrFail($contractId);

            if ($contract->status === 'terminated') {
                throw new InvalidStateTransitionException('This contract is already terminated.');
            }

            $contract->update(['status' => 'terminated']);

            return $contract;
        });
    }
}
