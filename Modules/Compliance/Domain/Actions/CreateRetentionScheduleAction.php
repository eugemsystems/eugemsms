<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateRetentionScheduleData;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Domain\Actions\Action;

final class CreateRetentionScheduleAction extends Action
{
    public function execute(CreateRetentionScheduleData $data): RetentionSchedule
    {
        return $this->transaction(fn (): RetentionSchedule => RetentionSchedule::create([
            'school_id' => $data->schoolId,
            'record_class' => $data->recordClass,
            'table_names' => $data->tableNames,
            'retention_years' => $data->retentionYears,
            'retention_trigger' => $data->retentionTrigger,
            'disposal_method' => $data->disposalMethod,
            'legal_basis' => $data->legalBasis,
            'requires_review' => $data->requiresReview,
            'is_active' => true,
        ]));
    }
}
