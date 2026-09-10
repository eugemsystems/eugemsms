<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\CreateCustomReportData;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\CustomReport;

/**
 * ACT-CreateCustomReport (Book J INT-01 §2/BR-INT-01-004). Rejects an
 * unregistered entity or field at SAVE time too — not just at run
 * time — so a report author gets immediate feedback rather than a
 * silently-broken saved report.
 */
final class CreateCustomReportAction extends Action
{
    public function execute(CreateCustomReportData $data): CustomReport
    {
        if (ReportFieldRegistry::getEntity($data->primaryEntityKey) === null) {
            throw new InvalidArgumentException("Unregistered report entity '{$data->primaryEntityKey}'.");
        }

        foreach ($data->selectedFields as $selection) {
            if (ReportFieldRegistry::getField($selection['entity'], $selection['field']) === null) {
                throw new InvalidArgumentException("Unregistered report field '{$selection['entity']}.{$selection['field']}'.");
            }
        }

        return $this->transaction(fn (): CustomReport => CustomReport::create([
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'description' => $data->description,
            'primary_entity_key' => $data->primaryEntityKey,
            'selected_fields' => $data->selectedFields,
            'filters' => $data->filters,
            'group_by' => $data->groupBy,
            'aggregations' => $data->aggregations,
            'sort' => $data->sort,
            'chart_type' => $data->chartType,
            'created_by' => $data->createdByUserId,
        ]));
    }
}
