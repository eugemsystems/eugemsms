<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

/**
 * A check named in Book A CORE-12 §3 whose owning module (fiscalisation
 * queue depth, gateway reachability, trial balance status → FIN-*; SMS/
 * WhatsApp credit → comms) is not built yet. Same honest-placeholder
 * shape as `PendingIntegrityCheck`.
 */
final readonly class PendingHealthCheck implements HealthCheck
{
    public function __construct(
        private string $checkKey,
        private string $owningModule,
    ) {}

    public function checkKey(): string
    {
        return $this->checkKey;
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        return new HealthCheckResult(
            status: 'unhealthy',
            message: "Not yet available — ships with {$this->owningModule}.",
        );
    }
}
