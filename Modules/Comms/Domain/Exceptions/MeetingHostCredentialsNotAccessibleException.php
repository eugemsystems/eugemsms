<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-07 §4 ⭐/BR-COM-07-001 (AC-COM-07-001). `host_url`/
 * `passcode` are exposed only to the meeting's own host.
 */
final class MeetingHostCredentialsNotAccessibleException extends DomainException
{
    public static function forMeeting(int $meetingId, int $userId): self
    {
        return new self(
            "User #{$userId} is not the host of meeting #{$meetingId} and may not see its host credentials.",
            ['meeting_id' => $meetingId, 'user_id' => $userId],
        );
    }

    public function errorCode(): string
    {
        return 'MEETING_HOST_CREDENTIALS_NOT_ACCESSIBLE';
    }
}
