<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateStoreData;
use Modules\Stores\Models\Store;

/**
 * ACT-CreateStore (Book H1 FIN-09 §2).
 */
final class CreateStoreAction extends Action
{
    public function execute(CreateStoreData $data): Store
    {
        return $this->transaction(fn (): Store => Store::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'store_type' => $data->storeType,
            'custodian_staff_id' => $data->custodianStaffId,
            'cost_centre_id' => $data->costCentreId,
            'inventory_account_id' => $data->inventoryAccountId,
            'default_expense_account_id' => $data->defaultExpenseAccountId,
            'location' => $data->location,
            'costing_method' => $data->costingMethod,
            'requires_issue_approval' => $data->requiresIssueApproval,
            'allows_negative_stock' => $data->allowsNegativeStock,
            'is_active' => true,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
