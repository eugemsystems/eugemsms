<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\UpdateCustomReportData;
use Modules\Intelligence\Models\CustomReport;

/**
 * ACT-UpdateCustomReport (Book J INT-01 §5 screen table, "report
 * edit"). Renames or re-describes an already-saved report — the
 * query itself (entity, fields, filters) is defined once at save
 * time by `CreateCustomReportAction`'s own field-registry
 * validation and is not re-opened here; a different query is a new
 * report, built again in `Reports\Builder`.
 */
final class UpdateCustomReportAction extends Action
{
    public function execute(UpdateCustomReportData $data): CustomReport
    {
        $report = CustomReport::findOrFail($data->reportId);

        return $this->transaction(function () use ($report, $data): CustomReport {
            $report->update([
                'name' => $data->name,
                'description' => $data->description,
                'chart_type' => $data->chartType,
            ]);

            return $report->fresh();
        });
    }
}
