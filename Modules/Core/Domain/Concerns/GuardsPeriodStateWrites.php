<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use ReflectionProperty;

/**
 * BR-CORE-03-008: `academic_state`/`financial_state` may only change
 * through `ACT-TransitionPeriodState` — the single gateway for every
 * state change, which writes the immutable `period_state_transitions`
 * audit row (BR-CORE-03-009) in the same transaction. Any other write to
 * either column is refused here.
 *
 * `stateTransitionAuthorized` is a real class property (not an Eloquent
 * attribute), the same shape as `BelongsToSession::$periodOverrideApproved`
 * — `ACT-TransitionPeriodState` sets it to `true` immediately before
 * `save()`, and nothing else may. Read reflectively, the same way
 * `PeriodGuard` reads `periodOverrideApproved`: the `saving` closure only
 * knows its argument as the base `Model` type, not this trait's mixin.
 * Only guards *updates*: creating a year/term with an initial state
 * (`ACT-CreateAcademicYear`/`ACT-CreateTerm` setting `planned`) is
 * establishing a starting point, not transitioning away from one, so it
 * is not gated the same way.
 *
 * @mixin Model
 */
trait GuardsPeriodStateWrites
{
    public bool $stateTransitionAuthorized = false;

    protected static function bootGuardsPeriodStateWrites(): void
    {
        static::updating(function (Model $model): void {
            if (! $model->isDirty(['academic_state', 'financial_state'])) {
                return;
            }

            if (! self::isStateTransitionAuthorized($model)) {
                throw new InvalidStateTransitionException(
                    'academic_state/financial_state can only change through ACT-TransitionPeriodState.',
                    ['model' => $model::class],
                );
            }
        });
    }

    private static function isStateTransitionAuthorized(Model $model): bool
    {
        if (! property_exists($model, 'stateTransitionAuthorized')) {
            return false;
        }

        return (new ReflectionProperty($model, 'stateTransitionAuthorized'))->getValue($model) === true;
    }
}
