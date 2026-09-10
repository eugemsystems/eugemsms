<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;
use Modules\Core\Domain\Support\SchoolContext;

/**
 * ADR-003: a single shared database, every tenant-owned row filtered by
 * `school_id`. A query without a resolvable school context returns no
 * rows rather than every school's — "a query without a school context is
 * a security defect, not an oversight" (Volume 1 principle #4).
 *
 * @implements Scope<Model>
 */
final class SchoolScope implements Scope
{
    public const BYPASS_ABILITY = 'core.system.bypass_school_scope';

    public function apply(Builder $builder, Model $model): void
    {
        $schoolId = SchoolContext::currentId();

        if ($schoolId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('school_id'), $schoolId);
    }

    public static function assertBypassPermitted(string $modelClass): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if (! $user->can(self::BYPASS_ABILITY)) {
            throw new InsufficientScopeException(
                "The current user may not bypass the school scope for [{$modelClass}].",
                ['model' => $modelClass, 'ability' => self::BYPASS_ABILITY],
            );
        }

        Log::warning('School scope bypassed', [
            'model' => $modelClass,
            'user_id' => $user->getAuthIdentifier(),
            'called_from' => self::callingClass(),
        ]);
    }

    /**
     * @param  array<int, int>  $schoolIds
     */
    public static function assertSchoolsAccessible(array $schoolIds): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        $unauthorised = array_diff($schoolIds, $user->assignedSchoolIds());

        if ($unauthorised !== []) {
            throw new UnauthorisedSchoolAccessException(
                'The current user is not assigned to every requested school.',
                ['requested' => $schoolIds, 'unauthorised' => array_values($unauthorised)],
            );
        }
    }

    private static function callingClass(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6);

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? null;

            if ($class !== null && $class !== self::class) {
                return $class;
            }
        }

        return null;
    }
}
