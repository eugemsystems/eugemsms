<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Modules\Comms\Models\CalendarEvent;

/**
 * Book I COM-06 §3 ⭐/BR-COM-06-003 (AC-COM-06-002). Staff sees every
 * scope. A guardian/learner sees `whole_school` always, `staff` never,
 * and `section`/`level` only when the value matches their own
 * (a Form 2 parent's own `gradeLevelId` never equals an A-Level-only
 * event's `audience_scope_id`, so it is filtered out — the literal
 * mechanism the acceptance criterion describes). `class`/`house` are
 * recognised scope values with no real resolution wired yet — no
 * class-roster or house-membership lookup exists in this codebase for
 * COM-06 to call, so they default-deny for a non-staff viewer rather
 * than guess; this is a documented, honest boundary, not an oversight.
 */
final class CalendarAudienceFilter
{
    public function isVisible(CalendarEvent $event, bool $isStaff, ?int $sectionId, ?int $gradeLevelId): bool
    {
        if ($isStaff) {
            return true;
        }

        return match ($event->audience_scope) {
            'whole_school' => true,
            'staff' => false,
            'section' => $sectionId !== null && $event->audience_scope_id === $sectionId,
            'level' => $gradeLevelId !== null && $event->audience_scope_id === $gradeLevelId,
            default => false,
        };
    }
}
