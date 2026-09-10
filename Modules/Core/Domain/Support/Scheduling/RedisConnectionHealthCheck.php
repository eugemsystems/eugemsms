<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Illuminate\Support\Facades\Redis;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Throwable;

final class RedisConnectionHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'redis_connection';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $startedAt = microtime(true);

        try {
            Redis::connection()->ping();
        } catch (Throwable $e) {
            return new HealthCheckResult(status: 'unhealthy', value: 'unreachable', message: $e->getMessage());
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return new HealthCheckResult(
            status: $latencyMs > 200 ? 'degraded' : 'healthy',
            value: 'reachable',
            threshold: "{$latencyMs} ms",
        );
    }
}
