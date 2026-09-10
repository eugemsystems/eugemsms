<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureItem;
use Modules\Finance\Models\FeeStructureRule;

/**
 * ACT-CreateFeeStructure (Book B FIN-02 §2/§7). Creates version 1 of a
 * structure with its complete rule and item set in one transaction —
 * a structure with rules but no items, or items but no rules, is never
 * a valid intermediate state a reader can observe.
 */
final class CreateFeeStructureAction extends Action
{
    public function execute(CreateFeeStructureData $data): FeeStructure
    {
        return $this->transaction(function () use ($data): FeeStructure {
            $structure = FeeStructure::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'name' => $data->name,
                'version' => 1,
                'status' => $data->status,
                'priority' => $data->priority,
                'created_by' => $data->createdByUserId,
            ]);

            foreach ($data->rules as $rule) {
                FeeStructureRule::create([
                    'structure_id' => $structure->id,
                    'attribute' => $rule['attribute'],
                    'operator' => $rule['operator'],
                    'value' => $rule['value'],
                    'custom_field_key' => $rule['custom_field_key'] ?? null,
                ]);
            }

            foreach ($data->items as $item) {
                FeeStructureItem::create([
                    'school_id' => $data->schoolId,
                    'structure_id' => $structure->id,
                    'component_id' => $item['component_id'],
                    'billing_basis' => $item['billing_basis'],
                    'amount_minor' => $item['amount_minor'] ?? null,
                    'currency' => $item['currency'],
                    'unit_rate_minor' => $item['unit_rate_minor'] ?? null,
                    'unit_label' => $item['unit_label'] ?? null,
                    'minimum_minor' => $item['minimum_minor'] ?? null,
                    'maximum_minor' => $item['maximum_minor'] ?? null,
                    'tier_bands' => $item['tier_bands'] ?? null,
                    'subject_rate_map' => $item['subject_rate_map'] ?? null,
                    'is_prorated' => $item['is_prorated'] ?? true,
                    'proration_basis' => $item['proration_basis'] ?? 'day',
                    'charge_frequency' => $item['charge_frequency'] ?? 'termly',
                    'is_optional' => $item['is_optional'] ?? false,
                ]);
            }

            return $structure->load('rules', 'items');
        });
    }
}
