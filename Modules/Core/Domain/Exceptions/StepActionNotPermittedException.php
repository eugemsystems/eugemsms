<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * The current step's `can_reject`/`can_return` forbids the attempted
 * action.
 */
class StepActionNotPermittedException extends DomainException
{
    public function errorCode(): string
    {
        return 'STEP_ACTION_NOT_PERMITTED';
    }
}
