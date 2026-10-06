<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\ScheduleInterviewData;
use Modules\People\Models\Application;
use Modules\People\Models\Interview;

/**
 * ACT-ScheduleInterview (Book C PPL-02 §2). Books an applicant interview with a
 * panel of at least one member of this school. An applicant has one open
 * interview at a time.
 */
final class ScheduleInterviewAction extends Action
{
    public function execute(ScheduleInterviewData $data): Interview
    {
        $application = Application::findOrFail($data->applicationId);

        if ($data->scheduledAt->isPast()) {
            throw new InvalidArgumentException('An interview must be scheduled in the future.');
        }

        $panel = array_values(array_unique($data->panelUserIds));

        if ($panel === [] || $application->school->users()->whereIn('users.id', $panel)->count() !== count($panel)) {
            throw new InvalidArgumentException('The panel must be one or more members of this school.');
        }

        if (Interview::query()->where('application_id', $application->id)->whereNull('completed_at')->exists()) {
            throw new InvalidArgumentException('This applicant already has an interview waiting.');
        }

        return $this->transaction(fn (): Interview => Interview::create([
            'school_id' => $application->school_id,
            'application_id' => $application->id,
            'scheduled_at' => $data->scheduledAt->toDateTimeString(),
            'venue' => $data->venue,
            'panel_user_ids' => $panel,
        ]));
    }
}
