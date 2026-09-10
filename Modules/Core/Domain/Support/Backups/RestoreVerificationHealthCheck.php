<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Backups;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Modules\Core\Models\RestoreTest;

/**
 * Book A CORE-13 §3 ⭐ BR-CORE-13-003/BR-CORE-13-010: "last verified
 * restore" on the health dashboard. BR-CORE-13-003 requires a restore
 * test at least weekly — "a backup that has never been test-restored is
 * not a backup" is exactly what this check exists to catch going stale.
 */
final class RestoreVerificationHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'backup_last_verified_restore';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $lastPassedAt = RestoreTest::query()
            ->where('status', 'passed')
            ->max('tested_at');

        if ($lastPassedAt === null) {
            return new HealthCheckResult(status: 'unhealthy', message: 'No restore test has ever passed.');
        }

        $ageDays = (int) floor(now()->diffInDays($lastPassedAt, absolute: true));

        $status = match (true) {
            $ageDays > 14 => 'unhealthy',
            $ageDays > 8 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$ageDays}d ago",
            threshold: '<= 8d healthy, 8-14d degraded, > 14d unhealthy',
        );
    }
}
