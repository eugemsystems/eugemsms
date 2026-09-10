<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Str;
use Modules\Comms\Models\CalendarFeedToken;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-IssueCalendarFeedToken (Book I COM-06 §4/BR-COM-06-008).
 */
final class IssueCalendarFeedTokenAction extends Action
{
    public function execute(int $schoolId, int $userId, string $audienceScope, ?int $audienceScopeId = null): CalendarFeedToken
    {
        return $this->transaction(fn (): CalendarFeedToken => CalendarFeedToken::create([
            'school_id' => $schoolId,
            'token' => Str::random(64),
            'user_id' => $userId,
            'audience_scope' => $audienceScope,
            'audience_scope_id' => $audienceScopeId,
        ]));
    }
}
