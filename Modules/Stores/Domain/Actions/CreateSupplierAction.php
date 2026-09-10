<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateSupplierData;
use Modules\Stores\Models\Supplier;

/**
 * ACT-CreateSupplier (Book H1 FIN-08 §6/BR-FIN-08-001). Always created
 * `pending_approval` — a supplier that can receive an order the moment
 * it's typed in is exactly the fraud pattern this rule exists to stop.
 */
final class CreateSupplierAction extends Action
{
    public function execute(CreateSupplierData $data): Supplier
    {
        return $this->transaction(fn (): Supplier => Supplier::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'trading_name' => $data->tradingName,
            'supplier_type' => $data->supplierType,
            'bp_number' => $data->bpNumber,
            'vat_number' => $data->vatNumber,
            'company_registration' => $data->companyRegistration,
            'is_vat_registered' => $data->isVatRegistered,
            'contact_person' => $data->contactPerson,
            'phone' => $data->phone,
            'email' => $data->email,
            'preferred_currency' => $data->preferredCurrency,
            'payment_terms_days' => $data->paymentTermsDays,
            'control_account_id' => $data->controlAccountId,
            'status' => 'pending_approval',
            'created_by' => $data->createdByUserId,
        ]));
    }
}
