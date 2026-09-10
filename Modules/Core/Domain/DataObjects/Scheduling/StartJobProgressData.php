<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class StartJobProgressData
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public string $jobType,
        public string $title,
        public ?int $totalSteps = null,
    ) {}
}
