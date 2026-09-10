<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Scheduling;

use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

/**
 * Book A CORE-12 §3. One row of the system health check suite.
 */
interface HealthCheck
{
    public function checkKey(): string;

    /**
     * False for a check whose owning module or infrastructure doesn't
     * exist yet — same deferred-availability shape as `IntegrityCheck`.
     */
    public function isAvailable(): bool;

    public function run(): HealthCheckResult;
}
