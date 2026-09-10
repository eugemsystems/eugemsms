<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

/**
 * Book A CORE-12 §3. `jobs.created_at` is a raw unix timestamp integer
 * (the `database` queue driver's own column, not an Eloquent
 * timestamp), so age is computed by hand rather than via a cast.
 */
final class OldestQueuedJobHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'oldest_queued_job_age';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $oldest = DB::table('jobs')->min('created_at');

        if ($oldest === null) {
            return new HealthCheckResult(status: 'healthy', value: '0', message: 'No jobs queued.');
        }

        $ageMinutes = (int) floor(((int) now()->timestamp - (int) $oldest) / 60);

        $status = match (true) {
            $ageMinutes > 10 => 'unhealthy',
            $ageMinutes >= 1 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$ageMinutes} min",
            threshold: '< 1 min healthy, 1-10 min degraded, > 10 min unhealthy',
        );
    }
}
