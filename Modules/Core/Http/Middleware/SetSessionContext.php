<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\InvalidSessionContextException;
use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 5 / BR-CORE-03-005: the session context is
 * resolved on every request. A request that cannot resolve one is
 * rejected with MISSING_SESSION_CONTEXT, never defaulted silently.
 */
final class SetSessionContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $school = SchoolContext::current();

        if ($school === null) {
            return $next($request);
        }

        $user = $request->user();

        $preference = $user !== null
            ? UserSessionPreference::where('user_id', $user->id)->where('school_id', $school->id)->first()
            : null;

        $headerYearId = $request->header('X-Academic-Year-Id');
        $headerTermId = $request->header('X-Term-Id');

        if ($headerYearId !== null || $headerTermId !== null) {
            $this->setFromHeaders($school->id, $headerYearId, $headerTermId, $school);

            return $next($request);
        }

        $year = ($preference !== null ? $preference->academicYear : null) ?? $school->currentAcademicYear();

        if ($year === null) {
            throw new MissingSessionContextException('This school has no active academic year configured.');
        }

        $term = ($preference !== null ? $preference->term : null) ?? $year->currentTerm();

        SessionContext::set($year, $term);

        return $next($request);
    }

    /**
     * Volume 1 §9.1: a client may read a past or future session by naming it. The year must be one
     * of this school's own, and a term must belong to that year (the current year when only a term
     * is given) — anything else is refused rather than silently defaulted.
     */
    private function setFromHeaders(int $schoolId, ?string $yearHeader, ?string $termHeader, School $school): void
    {
        $year = $yearHeader !== null
            ? AcademicYear::query()->where('school_id', $schoolId)->whereKey((int) $yearHeader)->first()
            : $school->currentAcademicYear();

        if ($year === null) {
            throw new InvalidSessionContextException('That academic year is not one of this school\'s.', ['academic_year_id' => $yearHeader]);
        }

        $term = null;

        if ($termHeader !== null) {
            $term = Term::query()->where('academic_year_id', $year->id)->whereKey((int) $termHeader)->first();

            if ($term === null) {
                throw new InvalidSessionContextException('That term does not belong to the academic year.', ['term_id' => $termHeader]);
            }
        } else {
            $term = $year->currentTerm();
        }

        SessionContext::set($year, $term);
    }
}
