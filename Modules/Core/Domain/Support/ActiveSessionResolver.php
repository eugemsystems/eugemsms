<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use App\Models\User;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

/**
 * Companion to `ActiveSchoolResolver` for the (academic_year, term) half
 * of the same bug (2026-09-12 — user-reported: "the session selects
 * still having issues ... acting weird"). `SessionSwitcher::render()` was
 * reading `SessionContext::isSet()`, which is only ever true on the
 * handful of `{school}`-scoped pages that run `SetSessionContext` via
 * `InteractsWithSession` — everywhere else (dashboard, Users, the
 * switcher widget itself right after a switch-and-redirect) it fell
 * straight to null, so the dropdown showed "Select session" and every
 * checkmark disappeared even though `UserSessionPreference` had been
 * updated correctly. This does the same "preference, else current"
 * lookup `SetSessionContext` already does for a real request, but as a
 * plain, callable-anywhere lookup.
 *
 * `AcademicYear`/`Term` both use `BelongsToSchool`, whose `SchoolScope`
 * returns zero rows for any query with no ambient `SchoolContext` set
 * (BR-GLOBAL-010) — precisely the situation this resolver exists to run
 * in. So, like `SwitchSessionAction`, every lookup here goes through
 * `withoutGlobalScopes()` with an explicit `school_id` filter rather
 * than a `school()`/`academicYear()`/`term()` relation, which would
 * otherwise silently come back null regardless of what's in the
 * database.
 */
final class ActiveSessionResolver
{
    /**
     * @return array{0: int|null, 1: int|null} [academicYearId, termId]
     */
    public static function resolve(?User $user, ?int $schoolId): array
    {
        if ($user === null || $schoolId === null) {
            return [null, null];
        }

        $preference = UserSessionPreference::query()
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->first();

        $year = $preference?->academic_year_id !== null
            ? AcademicYear::withoutGlobalScopes()->where('id', $preference->academic_year_id)->where('school_id', $schoolId)->first()
            : null;

        $year ??= AcademicYear::withoutGlobalScopes()->where('school_id', $schoolId)->where('is_current', true)->first();

        if ($year === null) {
            return [null, null];
        }

        $term = $preference?->term_id !== null
            ? Term::withoutGlobalScopes()->where('id', $preference->term_id)->where('academic_year_id', $year->id)->first()
            : null;

        $term ??= Term::withoutGlobalScopes()->where('academic_year_id', $year->id)->where('is_current', true)->first();

        return [$year->id, $term?->id];
    }
}
