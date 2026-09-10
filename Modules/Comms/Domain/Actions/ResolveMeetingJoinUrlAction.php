<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ResolveMeetingJoinUrl (Book I COM-07 §5 ⭐/BR-COM-07-001
 * (AC-COM-07-001)). Resolves `join_url` ONLY — matching
 * `GET /api/v1/meetings/{ulid}/join`'s own literal contract. Never
 * returns `host_url`/`passcode` to ANY caller, host included; a host
 * fetches those through `ResolveMeetingHostCredentialsAction` instead.
 */
final class ResolveMeetingJoinUrlAction extends Action
{
    protected bool $transactional = false;

    public function execute(int $meetingId): ?string
    {
        return ScheduledMeeting::findOrFail($meetingId)->join_url;
    }
}
