<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateAttendanceReasonCodeData;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Core\Domain\Actions\Action;

final class CreateAttendanceReasonCodeAction extends Action
{
    public function execute(CreateAttendanceReasonCodeData $data): AttendanceReasonCode
    {
        return $this->transaction(fn (): AttendanceReasonCode => AttendanceReasonCode::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'counts_as_present' => $data->countsAsPresent,
            'counts_toward_percentage' => $data->countsTowardPercentage,
            'is_authorised' => $data->isAuthorised,
            'requires_document' => $data->requiresDocument,
            'suppresses_notification' => $data->suppressesNotification,
            'triggers_welfare_flag' => $data->triggersWelfareFlag,
            'is_active' => true,
        ]));
    }
}
