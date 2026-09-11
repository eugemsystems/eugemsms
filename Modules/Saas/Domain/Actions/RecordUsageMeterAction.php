<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Saas\Domain\DataObjects\RecordUsageData;
use Modules\Saas\Domain\Events\UsageSoftWarningCrossed;
use Modules\Saas\Domain\Exceptions\NoSubscriptionForTenantException;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\UsageMeter;

/**
 * ACT-RecordUsageMeter (Book J SAA-01 §4/BR-SAA-01-003/004
 * (AC-SAA-01-004)). Meant to run nightly per tenant per metric — the
 * same "wiring deferred, bookkeeping real" boundary
 * `Modules\Intelligence\Domain\Actions\DispatchWebhookAction` already
 * draws. `hard_limit_reached` blocks only the metric's own specific
 * over-limit action (`UsageLimitGuard::assertWithinLimit()`), never a
 * blanket lockout.
 */
final class RecordUsageMeterAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RecordUsageData $data): UsageMeter
    {
        $subscription = Subscription::query()
            ->where('tenant_id', $data->tenantId)
            ->latest('id')
            ->first();

        if ($subscription === null) {
            throw new NoSubscriptionForTenantException("Cannot record usage for tenant [{$data->tenantId}]: no subscription exists.", ['tenant_id' => $data->tenantId]);
        }

        $periodMonth = $data->periodMonth ?? Carbon::today()->format('Y-m');
        $limit = $subscription->plan->limitFor($data->metric);

        return $this->transaction(function () use ($data, $subscription, $periodMonth, $limit): UsageMeter {
            $meter = UsageMeter::query()->firstOrNew([
                'tenant_id' => $data->tenantId,
                'period_month' => $periodMonth,
                'metric' => $data->metric,
            ]);

            $wasSoftWarned = (bool) $meter->soft_warning_sent;

            $meter->subscription_id = $subscription->id;
            $meter->usage_value = $data->usageValue;
            $meter->limit_value = $limit;

            $thresholdPercent = (float) $this->settings->get('saas.usage_soft_warning_threshold_percent', new ScopeChain);

            $meter->hard_limit_reached = $limit !== null && $data->usageValue > $limit;
            $meter->soft_warning_sent = $wasSoftWarned
                || ($limit !== null && $limit > 0 && ($data->usageValue / $limit) * 100 >= $thresholdPercent);

            $meter->save();

            if (! $wasSoftWarned && $meter->soft_warning_sent) {
                event(new UsageSoftWarningCrossed($meter->fresh()));
            }

            return $meter->fresh();
        });
    }
}
