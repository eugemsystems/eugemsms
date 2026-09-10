<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\DataObjects\ChangeSupplierBankDetailsData;
use Modules\Stores\Domain\Events\SupplierBankDetailsChanged;
use Modules\Stores\Models\Supplier;

/**
 * ACT-ChangeSupplierBankDetails (Book H1 FIN-08 §6/BR-FIN-08-002 ⚠⚠/
 * AC-FIN-08-009). The classic payment-diversion fraud vector — a
 * requester who can also approve their own bank-detail change can
 * silently redirect every future payment. `account_number` is
 * encrypted at rest via `Supplier`'s own native `'encrypted'` cast.
 */
final class ChangeSupplierBankDetailsAction extends Action
{
    public function execute(ChangeSupplierBankDetailsData $data): Supplier
    {
        if ($data->approvedByUserId === $data->requestedByUserId) {
            throw new InvalidStateTransitionException(
                'A supplier bank detail change requires approval by a different user from the one requesting it (BR-FIN-08-002).',
                ['supplier_id' => $data->supplierId],
            );
        }

        $supplier = Supplier::findOrFail($data->supplierId);

        return $this->transaction(function () use ($supplier, $data): Supplier {
            $supplier->update([
                'bank_name' => $data->bankName,
                'bank_branch' => $data->bankBranch,
                'account_number' => $data->accountNumber,
                'account_name' => $data->accountName,
                'swift_code' => $data->swiftCode,
                'updated_by' => $data->approvedByUserId,
            ]);

            event(new SupplierBankDetailsChanged($supplier, $data->requestedByUserId, $data->approvedByUserId));

            return $supplier;
        });
    }
}
