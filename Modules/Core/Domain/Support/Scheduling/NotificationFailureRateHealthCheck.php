<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Scheduling;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;
use Modules\Core\Domain\DataObjects\Scheduling\HealthCheckResult;
use Modules\Core\Models\Notification;

final class NotificationFailureRateHealthCheck implements HealthCheck
{
    public function checkKey(): string
    {
        return 'notification_failure_rate_1h';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(): HealthCheckResult
    {
        $terminal = Notification::query()
            ->where('created_at', '>=', now()->subHour())
            ->whereIn('status', ['sent', 'delivered', 'failed'])
            ->count();

        if ($terminal === 0) {
            return new HealthCheckResult(status: 'healthy', value: '0%', message: 'No notifications sent in the last hour.');
        }

        $failed = Notification::query()
            ->where('created_at', '>=', now()->subHour())
            ->where('status', 'failed')
            ->count();

        $rate = round(($failed / $terminal) * 100, 1);

        $status = match (true) {
            $rate > 10 => 'unhealthy',
            $rate >= 2 => 'degraded',
            default => 'healthy',
        };

        return new HealthCheckResult(
            status: $status,
            value: "{$rate}%",
            threshold: '< 2% healthy, 2-10% degraded, > 10% unhealthy',
        );
    }
}
