<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Domain\DataObjects\ExecuteReportSpec;
use Modules\Intelligence\Domain\DataObjects\ReportResult;
use Modules\Intelligence\Models\CustomReport;

/**
 * ACT-RunSavedReport (Book J INT-01 §3 ⭐/BR-INT-01-005
 * (AC-INT-01-002/003)). Runs with `$strict = false` unconditionally —
 * a saved/shared report never errors out for the running viewer over
 * a field only its ORIGINAL author could see; it just quietly returns
 * less. This is the ONE path a report's own `report_id` is threaded
 * through into `report_executions`.
 */
final class RunSavedReportAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly ExecuteCustomReportAction $executeCustomReport,
    ) {}

    /**
     * @param  array<int, int>|null  $consolidateSchoolIds
     */
    public function execute(int $reportId, User $runner, ?array $consolidateSchoolIds = null): ReportResult
    {
        $report = CustomReport::findOrFail($reportId);

        return $this->executeCustomReport->execute(
            new ExecuteReportSpec(
                schoolId: $report->school_id,
                primaryEntityKey: $report->primary_entity_key,
                selectedFields: $report->selected_fields,
                filters: $report->filters ?? [],
                groupBy: $report->group_by ?? [],
                aggregations: $report->aggregations ?? [],
                consolidateSchoolIds: $consolidateSchoolIds,
            ),
            $runner,
            strict: false,
            reportId: $report->id,
        );
    }
}
