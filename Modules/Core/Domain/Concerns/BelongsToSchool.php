<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;

/**
 * Book A Part 1.2. Every tenant table carries `school_id`, and every
 * tenant model uses this trait — a migration creating a tenant table
 * without `school_id` fails CI (BR-GLOBAL-010).
 *
 * @mixin Model
 */
trait BelongsToSchool
{
    protected static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('school_id'))) {
                $model->setAttribute('school_id', SchoolContext::currentId()
                    ?? throw new MissingSchoolContextException(
                        sprintf('Cannot create a [%s] without a resolvable school context.', static::class)
                    ));
            }
        });
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Escape hatch. Permission-gated and logged — BR-GLOBAL-012.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutSchoolScope(Builder $query): Builder
    {
        SchoolScope::assertBypassPermitted(static::class);

        return $query->withoutGlobalScope(SchoolScope::class);
    }

    /**
     * Explicit multi-school query for group-level reporting. Every school
     * requested must be one the caller is assigned to — BR-GLOBAL-013.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $schoolIds
     * @return Builder<static>
     */
    public function scopeForSchools(Builder $query, array $schoolIds): Builder
    {
        SchoolScope::assertSchoolsAccessible($schoolIds);

        return $query->withoutGlobalScope(SchoolScope::class)
            ->whereIn($this->qualifyColumn('school_id'), $schoolIds);
    }
}
