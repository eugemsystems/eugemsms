<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class OnboardingStepNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'ONBOARDING_STEP_NOT_FOUND';
    }
}
