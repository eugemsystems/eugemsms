<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use App\Models\User;
use Modules\Core\Models\UserSessionPreference;

/**
 * Book A CORE-02 §5 bugfix (2026-09-12 — user-reported: "when i choose a
 * different school or term nothing changes"). `SchoolSwitcher`/
 * `SessionSwitcher` persist the user's choice to `UserSessionPreference`
 * expecting `SetSchoolContext` middleware to read it back on the next
 * request — but that middleware (and the `serp.web` group it lives in)
 * is deliberately never applied to any real route in this app (every
 * `{school}`-scoped screen resolves its own context from the URL
 * instead, see `schools.php`'s own docblock on why). The result: every
 * screen and shared widget with NO `{school}` in its URL (the dashboard,
 * `Users\*`, `FeatureFlags\Index`, the sidebar's own computed
 * `$sessionsSchoolId`, and the switchers themselves) fell straight back
 * to `$user->primarySchool()`, completely ignoring what the user just
 * switched to — the switch silently did nothing outside whichever
 * `{school}` page happened to be open at the time.
 *
 * This is the missing piece: the same "preference, else primary" lookup
 * `SetSchoolContext` already does, extracted so every one of those
 * no-`{school}`-in-the-URL call sites can use it too, not just the
 * middleware nothing actually runs.
 */
final class ActiveSchoolResolver
{
    public static function resolveId(?User $user): ?int
    {
        if ($user === null) {
            return null;
        }

        $preferredId = UserSessionPreference::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->value('school_id');

        if ($preferredId !== null && $user->isAssignedToSchool((int) $preferredId)) {
            return (int) $preferredId;
        }

        return $user->primarySchool()?->id;
    }
}
