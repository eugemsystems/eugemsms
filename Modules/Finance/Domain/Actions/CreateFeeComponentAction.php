<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateFeeComponentData;
use Modules\Finance\Models\FeeComponent;

/**
 * ACT-CreateFeeComponent (Book B FIN-02 §2/§7).
 */
final class CreateFeeComponentAction extends Action
{
    public function execute(CreateFeeComponentData $data): FeeComponent
    {
        return $this->transaction(fn (): FeeComponent => FeeComponent::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'description' => $data->description,
            'category' => $data->category,
            'income_account_id' => $data->incomeAccountId,
            'debtor_account_id' => $data->debtorAccountId,
            'cost_centre_id' => $data->costCentreId,
            'default_currency' => $data->defaultCurrency,
            'is_refundable' => $data->isRefundable,
            'is_mandatory' => $data->isMandatory,
            'is_fiscalisable' => $data->isFiscalisable,
            'tax_category' => $data->taxCategory,
            'allocation_priority' => $data->allocationPriority,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
