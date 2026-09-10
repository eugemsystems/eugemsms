<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\Supplier;

/**
 * ACT-BlacklistSupplier (Book H1 FIN-08 §6/BR-FIN-08-026/AC-FIN-08-012).
 * Blacklisting never touches an existing approved order or invoice —
 * `Supplier::canReceiveOrders()` is the only place this status is
 * checked, and only `CreatePurchaseOrderAction` ever calls it.
 */
final class BlacklistSupplierAction extends Action
{
    public function execute(int $supplierId, string $reason): Supplier
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to blacklist a supplier.']);
        }

        $supplier = Supplier::findOrFail($supplierId);

        return $this->transaction(fn (): Supplier => tap($supplier)->update([
            'status' => 'blacklisted',
            'blacklist_reason' => $reason,
        ]));
    }
}
