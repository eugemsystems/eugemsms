<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\CreateTimetableExceptionData;
use Modules\Academic\Models\TimetableException;
use Modules\Core\Domain\Actions\Action;

final class CreateTimetableExceptionAction extends Action
{
    public function execute(CreateTimetableExceptionData $data): TimetableException
    {
        return $this->transaction(fn (): TimetableException => TimetableException::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'exception_date' => $data->exceptionDate->toDateString(),
            'exception_type' => $data->exceptionType,
            'alternative_structure_id' => $data->alternativeStructureId,
            'affected_scope' => $data->affectedScope,
            'scope_id' => $data->scopeId,
            'reason' => $data->reason,
            'suppresses_attendance' => $data->suppressesAttendance,
            'created_by' => $data->createdByUserId,
            'created_at' => Carbon::now(),
        ]));
    }
}
