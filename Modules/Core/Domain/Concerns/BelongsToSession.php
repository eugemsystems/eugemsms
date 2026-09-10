<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Scopes\SessionScope;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Domain\Support\SessionContext;

/**
 * Book A Part 1.3. Every academic or financial table carries
 * `academic_year_id` and (usually) `term_id` — see §0.4 — and is scoped
 * and period-guarded through this trait.
 *
 * @mixin Model
 */
trait BelongsToSession
{
    /**
     * A real class property (not an Eloquent attribute) so it never
     * touches `$attributes` or gets persisted. A caller sets this after
     * an approved SOFT_CLOSED-period override before saving — see
     * `PeriodGuard` and BR-CORE-03-020.
     */
    public bool $periodOverrideApproved = false;

    protected static function bootBelongsToSession(): void
    {
        static::addGlobalScope(new SessionScope);

        // Eloquent fires `saving` *before* `creating` on a new model, so
        // guarding from `saving` alone would run before academic_year_id /
        // term_id are populated below. The create path is guarded from
        // inside `creating`, once the FK columns are set; the update path
        // (where they already exist) is guarded from `updating`.
        static::creating(function (Model $model): void {
            $model->setAttribute('academic_year_id', $model->getAttribute('academic_year_id') ?? SessionContext::yearId());
            $model->setAttribute('term_id', $model->getAttribute('term_id') ?? SessionContext::termId());

            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });
    }
}
