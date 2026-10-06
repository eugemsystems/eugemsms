<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Models\SupplierContract;

/**
 * ACT-RenewSupplierContract (Book H1 FIN-08 §6/BR-FIN-08-024). Extends an active or
 * expiring contract to a later end date and returns it to `active`, so the expiry
 * alert can fire again at the next notice window. A terminated or expired contract
 * is not revived — a new one is recorded instead.
 */
final class RenewSupplierContractAction extends Action
{
    public function execute(int $contractId, CarbonInterface $newEndsOn): SupplierContract
    {
        return $this->transaction(function () use ($contractId, $newEndsOn): SupplierContract {
            $contract = SupplierContract::query()->lockForUpdate()->findOrFail($contractId);

            if (! in_array($contract->status, ['active', 'expiring'], true)) {
                throw new InvalidStateTransitionException('Only an active or expiring contract can be renewed.');
            }

            if ($contract->ends_on !== null && $newEndsOn->lessThanOrEqualTo($contract->ends_on)) {
                throw new InvalidStateTransitionException('A renewal must end after the current end date.');
            }

            $contract->update(['ends_on' => $newEndsOn->toDateString(), 'status' => 'active']);

            return $contract;
        });
    }
}
