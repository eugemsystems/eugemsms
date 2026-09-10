<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class ScheduledTaskDefinitionData
{
    public function __construct(
        public string $key,
        public string $moduleCode,
        public string $name,
        public string $command,
        public string $scheduleExpression,
        public ?string $description = null,
        public bool $isEnabled = true,
        public bool $isPerSchool = false,
        public int $timeoutSeconds = 300,
        public bool $alertOnFailure = true,
        public ?int $alertIfNotRunWithinMinutes = null,
    ) {}
}
