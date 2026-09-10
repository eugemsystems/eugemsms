<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateInventoryItemData;
use Modules\Stores\Models\InventoryItem;

/**
 * ACT-CreateInventoryItem (Book H1 FIN-09 §2/BR-FIN-09-013).
 */
final class CreateInventoryItemAction extends Action
{
    public function execute(CreateInventoryItemData $data): InventoryItem
    {
        return $this->transaction(fn (): InventoryItem => InventoryItem::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'category_id' => $data->categoryId,
            'base_unit' => $data->baseUnit,
            'purchase_unit' => $data->purchaseUnit,
            'purchase_conversion' => $data->purchaseConversion,
            'issue_unit' => $data->issueUnit,
            'issue_conversion' => $data->issueConversion,
            'is_perishable' => $data->isPerishable,
            'requires_batch_tracking' => $data->requiresBatchTracking,
            'shelf_life_days' => $data->shelfLifeDays,
            'is_high_risk' => $data->isHighRisk,
            'is_saleable' => $data->isSaleable,
            'sale_price_minor' => $data->salePriceMinor,
            'sale_currency' => $data->saleCurrency,
            'sale_fee_component_id' => $data->saleFeeComponentId,
            'is_capitalisable' => $data->isCapitalisable,
            'capitalisation_threshold_minor' => $data->capitalisationThresholdMinor,
            'expense_account_id' => $data->expenseAccountId,
            'standard_cost_minor' => $data->standardCostMinor,
            'standard_cost_currency' => $data->standardCostCurrency,
            'is_active' => true,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
