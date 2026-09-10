<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Audit;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Audit\RecordActivityData;
use Modules\Core\Models\ActivityLogEntry;

/**
 * ACT-RecordActivity (Book A CORE-08 §3/BR-CORE-08-001..004). Called
 * from `LogActivityJob`, never directly from a model event — writes
 * are queued (BR-CORE-08-014) precisely so this never adds latency to
 * the request that triggered it.
 */
final class RecordActivityAction extends Action
{
    public function execute(RecordActivityData $data): ActivityLogEntry
    {
        return $this->transaction(fn (): ActivityLogEntry => ActivityLogEntry::create([
            'school_id' => $data->schoolId,
            'log_name' => $data->logName,
            'description' => $data->description,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'causer_type' => $data->causerType,
            'causer_id' => $data->causerId,
            'event' => $data->event,
            'properties' => $data->properties,
            'batch_uuid' => $data->batchUuid,
            'ip_address' => $data->ip,
            'user_agent' => $data->userAgent,
            'request_id' => $data->requestId,
            'impersonator_id' => $data->impersonatorId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'created_at' => now(),
        ]));
    }
}
