<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
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

        $year = ($preference !== null ? $preference->academicYear : null) ?? $school->currentAcademicYear();

        if ($year === null) {
            throw new MissingSessionContextException('This school has no active academic year configured.');
        }

        $term = ($preference !== null ? $preference->term : null) ?? $year->currentTerm();

        SessionContext::set($year, $term);

        return $next($request);
    }
}
