<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateIssuableItemData;
use Modules\Boarding\Models\IssuableItem;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateIssuableItem (Book F BRD-05 §2/§4).
 */
final class CreateIssuableItemAction extends Action
{
    public function execute(CreateIssuableItemData $data): IssuableItem
    {
        return $this->transaction(fn (): IssuableItem => IssuableItem::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'category' => $data->category,
            'inventory_item_id' => $data->inventoryItemId,
            'is_returnable' => $data->isReturnable,
            'is_launderable' => $data->isLaunderable,
            'replacement_cost_minor' => $data->replacementCostMinor,
            'currency' => $data->currency,
            'expected_lifespan_terms' => $data->expectedLifespanTerms,
            'requires_tagging' => $data->requiresTagging,
        ]));
    }
}
