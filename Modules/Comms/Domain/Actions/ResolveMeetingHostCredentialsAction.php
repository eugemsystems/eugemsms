<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Exceptions\MeetingHostCredentialsNotAccessibleException;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Staff;

/**
 * ACT-ResolveMeetingHostCredentials (Book I COM-07 §5 ⭐/BR-COM-07-001
 * (AC-COM-07-001)). `host_url`/`passcode` are exposed only to the
 * meeting's own host.
 */
final class ResolveMeetingHostCredentialsAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{host_url: ?string, passcode: ?string}
     */
    public function execute(int $meetingId, int $requestingUserId): array
    {
        $meeting = ScheduledMeeting::findOrFail($meetingId);
        $host = Staff::where('school_id', $meeting->school_id)->find($meeting->host_staff_id);

        if ($host === null || $host->user_id !== $requestingUserId) {
            throw MeetingHostCredentialsNotAccessibleException::forMeeting($meetingId, $requestingUserId);
        }

        return ['host_url' => $meeting->host_url, 'passcode' => $meeting->passcode];
    }
}
