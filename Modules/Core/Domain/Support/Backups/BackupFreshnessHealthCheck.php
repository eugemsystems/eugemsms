<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Backups;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Modules\Core\Models\Backup;

/**
 * Book A CORE-13 §3/BR-CORE-13-010: "last successful backup" on the
 * system health dashboard — plugs into CORE-12's `HealthCheckRegistry`
 * rather than building a second status surface. BR-CORE-13-001 requires
 * at least daily backups, so a gap over a day is already notable.
 */
final class BackupFreshnessHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'backup_last_success';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $lastCompletedAt = Backup::query()
            ->where('scope', 'system')
            ->whereIn('status', ['completed', 'verified'])
            ->max('completed_at');

        if ($lastCompletedAt === null) {
            return new HealthCheckResult(status: 'unhealthy', message: 'No successful backup has ever completed.');
        }

        $ageHours = (int) floor(now()->diffInHours($lastCompletedAt, absolute: true));

        $status = match (true) {
            $ageHours > 48 => 'unhealthy',
            $ageHours > 25 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$ageHours}h ago",
            threshold: '<= 25h healthy, 25-48h degraded, > 48h unhealthy',
        );
    }
}
