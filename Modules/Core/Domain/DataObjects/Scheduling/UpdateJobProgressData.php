<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class UpdateJobProgressData
{
    public function __construct(
        public int $jobProgressId,
        public ?int $completedSteps = null,
        public ?string $currentMessage = null,
    ) {}
}
