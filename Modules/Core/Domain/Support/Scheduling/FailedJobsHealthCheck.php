<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

/**
 * Book A CORE-12 §3. Jobs that landed in `failed_jobs` in the last 24
 * hours (BR-CORE-12-004's 30-day retention window is for the UI's
 * retry list, not this rolling health window).
 */
final class FailedJobsHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'failed_jobs_24h';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $count = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->count();

        $status = match (true) {
            $count > 10 => 'unhealthy',
            $count >= 1 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: (string) $count,
            threshold: '0 healthy, 1-10 degraded, > 10 unhealthy',
        );
    }
}
