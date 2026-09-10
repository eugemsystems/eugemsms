<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class StartScheduledTaskRunData
{
    public function __construct(
        public string $taskKey,
        public ?int $schoolId = null,
    ) {}
}
