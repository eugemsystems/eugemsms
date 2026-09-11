<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Saas\Domain\Exceptions\UsageLimitExceededException;
use Modules\Saas\Models\UsageMeter;

/**
 * Book J SAA-01 §4/BR-SAA-01-004 (AC-SAA-01-004) — the reusable check a
 * hard-limited operation calls before it proceeds. A tenant's learner
 * band exceeded blocks only new enrolment; every other function stays
 * available, since nothing else in the platform calls this guard.
 *
 * Not yet wired into `Modules\People\Domain\Actions\CreateStudentAction`
 * or `ConvertApplicationToStudentAction` — that integration crosses
 * into a module this pass does not touch; this guard is the
 * fully-tested piece that call site adds in a later pass, the same
 * "real bookkeeping, wiring deferred" boundary this book already draws
 * elsewhere (see `RecordUsageMeterAction`).
 */
final class UsageLimitGuard
{
    public static function assertWithinLimit(int $tenantId, string $metric): void
    {
        $meter = UsageMeter::query()
            ->where('tenant_id', $tenantId)
            ->where('metric', $metric)
            ->where('period_month', Carbon::today()->format('Y-m'))
            ->first();

        if ($meter?->hard_limit_reached === true) {
            throw new UsageLimitExceededException(
                "Tenant [{$tenantId}] has reached its [{$metric}] limit for this billing period.",
                ['tenant_id' => $tenantId, 'metric' => $metric, 'usage_value' => $meter->usage_value, 'limit_value' => $meter->limit_value],
            );
        }
    }

    public static function assertLearnerEnrolmentAllowed(int $tenantId): void
    {
        self::assertWithinLimit($tenantId, UsageMeter::METRIC_ACTIVE_LEARNERS);
    }
}
