<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\PollPendingIntentData;
use Modules\Finance\Domain\DataObjects\SettleGatewayPaymentData;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;

/**
 * ACT-PollPendingIntent (Book B FIN-05 §7/BR-FIN-05-007/008
 * (AC-FIN-05-004)). The webhook fallback — "webhooks fail; the
 * polling fallback means a parent who paid always ends up receipted."
 * A real scheduled job would call this on a decaying schedule (1, 2,
 * 5, 10, 30 minutes); that schedule itself is not wired here (no job
 * infrastructure exists yet for it), but every poll this action
 * performs is fully real and settles exactly like a webhook would.
 */
final class PollPendingIntentAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly SettleGatewayPaymentAction $settlePayment,
    ) {}

    public function execute(PollPendingIntentData $data): PaymentIntent
    {
        $intent = PaymentIntent::findOrFail($data->paymentIntentId);

        if (in_array($intent->status, ['succeeded', 'cancelled', 'refunded'], true)) {
            return $intent;
        }

        if ($intent->isExpired() && $intent->status !== 'expired') {
            $intent->update(['status' => 'expired']);
        }

        $gateway = PaymentGateway::findOrFail($intent->gateway_id);
        $driver = $this->drivers->resolve($gateway->driver);
        $result = $driver->poll($intent);

        $intent->update([
            'poll_attempts' => $intent->poll_attempts + 1,
            'last_polled_at' => Carbon::now(),
        ]);

        if ($result->isSettled()) {
            // BR-FIN-05-008: a late settlement on an already-expired
            // intent is still honoured and receipted — settling here
            // doesn't check `isExpired()` again.
            return $this->settlePayment->execute(new SettleGatewayPaymentData(
                paymentIntentId: $intent->id,
                processedByUserId: $data->processedByUserId,
                gatewayReference: $result->gatewayReference ?? $intent->gateway_reference,
                amountMinor: $intent->amount_minor,
                feeMinor: $result->feeMinor,
            ));
        }

        if (in_array($result->status, ['failed', 'cancelled'], true)) {
            $intent->update([
                'status' => $result->status,
                'failure_code' => $result->failureCode,
                'failure_message' => $result->failureMessage,
            ]);
        }

        return $intent->fresh();
    }
}
