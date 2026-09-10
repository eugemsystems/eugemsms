<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Core\Models\UserSessionPreference;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 4. Resolves the active school from a validated
 * `X-School-Id` header, the user's stored session preference, or their
 * primary school assignment — in that order.
 */
final class SetSchoolContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $school = $this->resolveSchool($request, $user);

        if ($school === null) {
            throw new MissingSchoolContextException('Could not resolve an active school for the current user.');
        }

        SchoolContext::set($school);

        return $next($request);
    }

    private function resolveSchool(Request $request, User $user): ?School
    {
        $headerId = $request->header('X-School-Id');

        if ($headerId !== null) {
            $school = School::query()->whereKey((int) $headerId)->first();

            if ($school === null || ! $user->isAssignedToSchool($school->id)) {
                throw new UnauthorisedSchoolAccessException(
                    'The requested school is not one you are assigned to.',
                    ['school_id' => $headerId],
                );
            }

            return $school;
        }

        $preference = UserSessionPreference::where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->first();

        if ($preference !== null) {
            return School::query()->whereKey($preference->school_id)->first();
        }

        return $user->primarySchool();
    }
}
