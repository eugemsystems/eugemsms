<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Contracts\HasPeriodType;
use Modules\Core\Domain\Exceptions\PeriodLockedException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

/**
 * BR-CORE-03-020: `OPEN` allows writes; `SOFT_CLOSED` allows writes only
 * with an approved override; `LOCKED`/`ARCHIVED`/`PLANNED` refuse them.
 * Reads are always permitted regardless of state — locking restricts
 * writing, never viewing (BR-CORE-03-021).
 */
final class PeriodGuard
{
    public static function assertWritable(Model $model): void
    {
        $period = self::resolvePeriod($model);

        if ($period === null) {
            return;
        }

        $type = $model instanceof HasPeriodType ? $model->periodType() : PeriodType::Financial;
        $state = $period->stateFor($type);

        if ($state->isWritable()) {
            return;
        }

        if ($state->isWritableWithOverride() && self::hasApprovedOverride($model)) {
            return;
        }

        throw new PeriodLockedException(
            sprintf('This %s period is %s and cannot accept writes.', $type->value, $state->value),
            [
                'period_type' => $type->value,
                'state' => $state->value,
                'model' => $model::class,
            ],
        );
    }

    private static function resolvePeriod(Model $model): Term|AcademicYear|null
    {
        $termId = $model->getAttribute('term_id');

        if ($termId !== null) {
            return Term::withoutGlobalScopes()->whereKey($termId)->first();
        }

        $yearId = $model->getAttribute('academic_year_id');

        if ($yearId !== null) {
            return AcademicYear::withoutGlobalScopes()->whereKey($yearId)->first();
        }

        return null;
    }

    /**
     * `periodOverrideApproved` is a real class property declared by
     * `BelongsToSession` (not every `Model` has it), read reflectively
     * since PeriodGuard only knows its caller as the base `Model` type.
     */
    private static function hasApprovedOverride(Model $model): bool
    {
        if (! property_exists($model, 'periodOverrideApproved')) {
            return false;
        }

        return (new \ReflectionProperty($model, 'periodOverrideApproved'))->getValue($model) === true;
    }
}
