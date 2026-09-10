<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

/**
 * Book A CORE-12 §3. Counts rows waiting in the `jobs` table (the
 * `database` queue driver's own storage) regardless of which queue
 * connection is configured.
 */
final class QueueDepthHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'queue_depth';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $depth = (int) DB::table('jobs')->count();

        $status = match (true) {
            $depth > 1000 => 'unhealthy',
            $depth >= 100 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: (string) $depth,
            threshold: '< 100 healthy, 100-1000 degraded, > 1000 unhealthy',
        );
    }
}
