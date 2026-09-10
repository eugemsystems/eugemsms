<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Throwable;

final class DatabaseConnectionHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'database_connection';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $startedAt = microtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            return new HealthCheckResult(status: 'unhealthy', message: $e->getMessage());
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $status = match (true) {
            $latencyMs > 200 => 'unhealthy',
            $latencyMs >= 50 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$latencyMs} ms",
            threshold: '< 50ms healthy, 50-200ms degraded, > 200ms unhealthy',
        );
    }
}
