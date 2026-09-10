<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class CompleteScheduledTaskRunData
{
    public function __construct(
        public int $runId,
        public string $status,
        public ?string $output = null,
        public ?string $error = null,
    ) {}
}
