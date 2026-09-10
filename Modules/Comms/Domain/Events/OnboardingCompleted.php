<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\OnboardingProgress;

final class OnboardingCompleted
{
    public function __construct(
        public readonly OnboardingProgress $progress,
    ) {}
}
