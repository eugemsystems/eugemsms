<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\Supplier;

/**
 * ACT-SetSupplierContractorStatus (Book H2 OPS-02 §6). The missing
 * piece `Ops\Maintenance\Contractors` needs: nothing elsewhere flags a
 * supplier as a contractor at all, so this is a plain toggle, mirroring
 * `BlacklistSupplierAction`'s own shape (a single-purpose status
 * change, not a generic update).
 */
final class SetSupplierContractorStatusAction extends Action
{
    public function execute(int $supplierId, bool $isContractor): Supplier
    {
        $supplier = Supplier::findOrFail($supplierId);

        return $this->transaction(fn (): Supplier => tap($supplier)->update([
            'is_contractor' => $isContractor,
        ]));
    }
}
