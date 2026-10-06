<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateSupplierContractData;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierContract;

/**
 * ACT-CreateSupplierContract (Book H1 FIN-08 §6/BR-FIN-08-024). Records a contract
 * with a supplier so its expiry can be watched. A blacklisted supplier cannot be
 * given a new contract; an end date must follow the start; the renewal notice only
 * makes sense for a contract that ends; and the number is unique within the school.
 */
final class CreateSupplierContractAction extends Action
{
    public const array TYPES = ['supply', 'service', 'lease', 'maintenance', 'framework'];

    public function execute(CreateSupplierContractData $data): SupplierContract
    {
        $errors = [];

        if (! in_array($data->contractType, self::TYPES, true)) {
            $errors['contractType'] = 'Choose a valid contract type.';
        }

        if ($data->endsOn !== null && $data->endsOn->lessThanOrEqualTo($data->startsOn)) {
            $errors['endsOn'] = 'The end date must be after the start date.';
        }

        if ($data->renewalNoticeDays !== null && ($data->endsOn === null || $data->renewalNoticeDays < 1)) {
            $errors['renewalNoticeDays'] = 'A renewal notice needs an end date and at least one day.';
        }

        if ($data->valueMinor !== null && $data->valueMinor < 0) {
            $errors['valueMinor'] = 'A contract value cannot be negative.';
        }

        $supplier = Supplier::query()->find($data->supplierId);

        if ($supplier === null || $supplier->school_id !== $data->schoolId) {
            $errors['supplierId'] = 'Choose one of this school\'s suppliers.';
        } elseif ($supplier->status === 'blacklisted') {
            $errors['supplierId'] = 'A blacklisted supplier cannot be given a new contract.';
        }

        if (SupplierContract::query()->where('contract_number', $data->contractNumber)->exists()) {
            $errors['contractNumber'] = 'That contract number is already in use.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $this->transaction(fn (): SupplierContract => SupplierContract::create([
            'school_id' => $data->schoolId,
            'supplier_id' => $data->supplierId,
            'contract_number' => $data->contractNumber,
            'title' => $data->title,
            'contract_type' => $data->contractType,
            'starts_on' => $data->startsOn->toDateString(),
            'ends_on' => $data->endsOn?->toDateString(),
            'value_minor' => $data->valueMinor,
            'currency' => $data->currency,
            'renewal_notice_days' => $data->renewalNoticeDays,
            'auto_renew' => $data->autoRenew,
            'owner_staff_id' => $data->ownerStaffId,
            'status' => 'active',
        ]));
    }
}
