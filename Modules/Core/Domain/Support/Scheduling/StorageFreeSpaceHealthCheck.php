<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;

final class StorageFreeSpaceHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'storage_free_space';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);

        if ($free === false || $total === false || $total <= 0.0) {
            return new HealthCheckResult(status: 'unhealthy', message: 'Unable to read disk space.');
        }

        $percentFree = round(($free / $total) * 100, 1);

        $status = match (true) {
            $percentFree < 10 => 'unhealthy',
            $percentFree <= 20 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$percentFree}%",
            threshold: '> 20% healthy, 10-20% degraded, < 10% unhealthy',
        );
    }
}
