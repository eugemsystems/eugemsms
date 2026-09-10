<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\ReviseFeeStructureData;
use Modules\Finance\Models\FeeStructure;
use Modules\Finance\Models\FeeStructureItem;
use Modules\Finance\Models\FeeStructureRule;

/**
 * ACT-ReviseFeeStructure (Book B FIN-02 §2/BR-FIN-02-011). Creates
 * version n+1 as a new draft row — the structure this revises stays
 * exactly as it was, still linked to every assignment it already
 * produced (AC-FIN-02-009). Activating the new draft
 * (`ActivateFeeStructureAction`) is what actually supersedes it.
 */
final class ReviseFeeStructureAction extends Action
{
    public function execute(ReviseFeeStructureData $data): FeeStructure
    {
        $current = FeeStructure::findOrFail($data->structureId);

        return $this->transaction(function () use ($current, $data): FeeStructure {
            $revision = FeeStructure::create([
                'school_id' => $current->school_id,
                'academic_year_id' => $current->academic_year_id,
                'term_id' => $current->term_id,
                'name' => $current->name,
                'version' => $current->version + 1,
                'status' => 'draft',
                'priority' => $current->priority,
                'effective_from' => $current->effective_from,
                'effective_to' => $current->effective_to,
                'created_by' => $data->revisedByUserId,
            ]);

            foreach ($data->rules as $rule) {
                FeeStructureRule::create([
                    'structure_id' => $revision->id,
                    'attribute' => $rule['attribute'],
                    'operator' => $rule['operator'],
                    'value' => $rule['value'],
                    'custom_field_key' => $rule['custom_field_key'] ?? null,
                ]);
            }

            foreach ($data->items as $item) {
                FeeStructureItem::create([
                    'school_id' => $current->school_id,
                    'structure_id' => $revision->id,
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

            return $revision->load('rules', 'items');
        });
    }
}
