<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateGovernanceMinuteData;
use Modules\Compliance\Models\GovernanceMinute;
use Modules\Core\Domain\Actions\Action;

final class CreateGovernanceMinuteAction extends Action
{
    public function execute(CreateGovernanceMinuteData $data): GovernanceMinute
    {
        return $this->transaction(fn (): GovernanceMinute => GovernanceMinute::create([
            'school_id' => $data->schoolId,
            'body' => $data->body,
            'meeting_date' => $data->meetingDate,
            'attendees' => $data->attendees,
            'minutes_file_id' => $data->minutesFileId,
            'resolutions' => $data->resolutions,
            'confidentiality' => $data->confidentiality,
            'access_role_ids' => $data->accessRoleIds,
        ]));
    }
}
