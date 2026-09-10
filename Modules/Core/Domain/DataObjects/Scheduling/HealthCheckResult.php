<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Scheduling;

final readonly class HealthCheckResult
{
    public function __construct(
        public string $status,
        public ?string $value = null,
        public ?string $threshold = null,
        public ?string $message = null,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === 'healthy';
    }

    public function isUnhealthy(): bool
    {
        return $this->status === 'unhealthy';
    }
}
