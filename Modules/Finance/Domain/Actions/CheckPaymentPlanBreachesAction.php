<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Models\PaymentPlan;
use Modules\Finance\Models\PaymentPlanInstalment;

/**
 * ACT-CheckPaymentPlanBreaches (Book B FIN-03 §4/BR-FIN-03-017). An
 * instalment missed by more than `finance.payment_plan_grace_days`
 * marks the whole plan `breached` — which, per BR-FIN-03-016, is what
 * makes the reminder ladder start firing again for its covered
 * invoices (the ladder's own suppression check only exempts
 * `active`, non-breached plans). Scheduled nightly, school-agnostic —
 * every active plan across every school is checked in one pass.
 */
final class CheckPaymentPlanBreachesAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(): int
    {
        $breached = 0;

        PaymentPlan::withoutGlobalScopes()
            ->where('status', 'active')
            ->with('instalments')
            ->chunkById(200, function ($plans) use (&$breached): void {
                foreach ($plans as $plan) {
                    $graceDays = (int) $this->settings->get('finance.payment_plan_grace_days', new ScopeChain(schoolId: $plan->school_id));
                    $cutoff = Carbon::now()->subDays($graceDays);

                    $isBreached = $plan->instalments->contains(
                        fn (PaymentPlanInstalment $instalment): bool => in_array($instalment->status, ['pending', 'overdue', 'partial'], true)
                            && $instalment->due_date->lessThan($cutoff),
                    );

                    if ($isBreached) {
                        $plan->update(['status' => 'breached', 'breach_count' => $plan->breach_count + 1]);
                        $breached++;
                    }
                }
            });

        return $breached;
    }
}
