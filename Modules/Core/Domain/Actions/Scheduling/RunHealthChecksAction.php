<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Audit\RecordSecurityEventAction;
use Modules\Core\Domain\DataObjects\Audit\RecordSecurityEventData;
use Modules\Core\Domain\DataObjects\Scheduling\RunHealthChecksData;
use Modules\Core\Domain\Registry\HealthCheckRegistry;
use Modules\Core\Models\SystemHealthCheck;

/**
 * ACT-RunHealthChecks (Book A CORE-12 §3/BR-CORE-12-009). Runs every
 * registered, available check (or the subset named in `checkKeys`),
 * upserting one `system_health_checks` row per key — this table holds
 * the latest reading, not a history. An unhealthy result alerts the
 * vendor operations channel via a critical security event.
 */
final class RunHealthChecksAction extends Action
{
    public function __construct(
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @return array<int, SystemHealthCheck>
     */
    public function execute(RunHealthChecksData $data): array
    {
        $results = [];

        foreach (HealthCheckRegistry::all() as $checkKey => $check) {
            if ($data->checkKeys !== null && ! in_array($checkKey, $data->checkKeys, true)) {
                continue;
            }

            if (! $check->isAvailable()) {
                continue;
            }

            $result = $check->run();

            $record = $this->transaction(fn (): SystemHealthCheck => SystemHealthCheck::updateOrCreate(
                ['check_key' => $checkKey],
                [
                    'status' => $result->status,
                    'value' => $result->value,
                    'threshold' => $result->threshold,
                    'message' => $result->message,
                    'checked_at' => Carbon::now(),
                ],
            ));

            if ($result->isUnhealthy()) {
                $this->recordSecurityEvent->execute(new RecordSecurityEventData(
                    eventType: 'health_check_unhealthy',
                    severity: 'critical',
                    description: "Health check [{$checkKey}] reported unhealthy: ".($result->message ?? $result->value ?? 'no detail'),
                    context: ['check_key' => $checkKey, 'value' => $result->value, 'threshold' => $result->threshold],
                ));
            }

            $results[] = $record;
        }

        return $results;
    }
}
