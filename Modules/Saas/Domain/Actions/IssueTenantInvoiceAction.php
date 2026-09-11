<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\DataObjects\IssueTenantInvoiceData;
use Modules\Saas\Models\Subscription;
use Modules\Saas\Models\TenantInvoice;

/**
 * ACT-IssueTenantInvoice (Book J SAA-01 §2). Bills the plan's
 * monthly-equivalent price for the subscription's current learner
 * count unless the caller supplies explicit line items (a prorated
 * upgrade charge — see `ChangeSubscriptionPlanAction`).
 */
final class IssueTenantInvoiceAction extends Action
{
    public function execute(IssueTenantInvoiceData $data): TenantInvoice
    {
        $subscription = Subscription::query()->with('plan')->findOrFail($data->subscriptionId);

        $lineItems = $data->lineItems ?? [[
            'description' => "Subscription fee — {$subscription->plan->name}",
            'amount_minor' => $subscription->plan->monthlyPriceMinor($subscription->learner_count_at_billing ?? 0),
        ]];

        $subtotalMinor = (int) array_sum(array_column($lineItems, 'amount_minor'));

        return $this->transaction(fn (): TenantInvoice => TenantInvoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'TINV/'.Carbon::now()->format('Ymd').'/'.Str::upper(Str::random(6)),
            'period_month' => $data->periodMonth,
            'line_items' => $lineItems,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => 0,
            'total_minor' => $subtotalMinor,
            'currency' => $subscription->billing_currency,
            'due_date' => Carbon::today()->addDays($data->dueInDays ?? 14)->toDateString(),
            'status' => 'issued',
        ]));
    }
}
