<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Reporting\Domain\DataObjects\CreateReportDefinitionData;
use Modules\Reporting\Models\ReportDefinition;

/**
 * ACT-CreateReportDefinition (Book H3 FIN-12 §2/§6). A small,
 * create-only gap-filling Action found during this module's admin-UI
 * pass: `report_definitions` had a real migration/model/factory but
 * no Action anywhere in the domain layer ever created a row — only
 * `ReportDefinitionFactory` ever did (the same "model exists, no
 * Action ever wrote one" gap every prior book's admin-UI pass has hit
 * at least once), which left `CreateReportScheduleAction`'s own
 * required `reportDefinitionId` FK with nothing a school could ever
 * point it at. `GenerateTrialBalanceAction`/`GenerateIncomeStatementAction`
 * need no `ReportDefinition` row at all (both are pure queries over
 * `journal_lines`) — this Action exists only so `Reports\Schedules\Index`
 * has something real to schedule against.
 */
final class CreateReportDefinitionAction extends Action
{
    public function execute(CreateReportDefinitionData $data): ReportDefinition
    {
        return $this->transaction(fn (): ReportDefinition => ReportDefinition::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'report_type' => $data->reportType,
            'structure' => $data->structure,
            'comparative_periods' => $data->comparativePeriods,
            'show_variance' => $data->showVariance,
            'show_budget' => $data->showBudget,
            'is_system' => false,
        ]));
    }
}
