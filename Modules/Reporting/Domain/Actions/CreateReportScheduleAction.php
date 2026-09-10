<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Reporting\Domain\DataObjects\CreateReportScheduleData;
use Modules\Reporting\Models\ReportSchedule;

/**
 * ACT-CreateReportSchedule (Book H3 FIN-12 §2). Records the schedule
 * itself — no scheduler drains `next_run_at` yet, see
 * `ReportingServiceProvider`'s own docblock.
 */
final class CreateReportScheduleAction extends Action
{
    public function execute(CreateReportScheduleData $data): ReportSchedule
    {
        return $this->transaction(fn (): ReportSchedule => ReportSchedule::create([
            'school_id' => $data->schoolId,
            'report_definition_id' => $data->reportDefinitionId,
            'name' => $data->name,
            'frequency' => $data->frequency,
            'parameters' => $data->parameters,
            'recipients' => $data->recipients,
            'format' => $data->format,
            'is_active' => true,
        ]));
    }
}
