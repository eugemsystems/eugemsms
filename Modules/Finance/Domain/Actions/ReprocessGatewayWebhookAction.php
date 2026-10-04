<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\SettleGatewayPaymentData;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\PaymentIntent;

/**
 * ACT-ReprocessGatewayWebhook (Book B FIN-05 §8 — the webhook log
 * screen's own "replay for failed processing").
 * `IngestGatewayWebhookAction`'s replay-protection lookup matches on
 * `(driver, payload_hash)` *before* it even looks at `processing_status`
 * (BR-FIN-05-004) — correct for a genuine gateway redelivery, but it
 * means calling that same action again for an already-stored row,
 * failed or not, just returns it untouched. There was no path at all
 * to retry OUR OWN failed processing of a row we already have (e.g.
 * the matching intent didn't exist yet at delivery time but does now).
 * This action re-runs processing against that SAME stored row's own
 * `raw_payload`/`raw_headers` — it never re-verifies the signature
 * (already recorded) and never touches `payload_hash`/the replay-
 * protection mechanism at all.
 */
final class ReprocessGatewayWebhookAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly SettleGatewayPaymentAction $settlePayment,
    ) {}

    public function execute(int $gatewayWebhookId, int $processedByUserId, ?int $creditBalanceAccountId = null, ?int $suspenseAccountId = null): GatewayWebhook
    {
        $webhook = GatewayWebhook::findOrFail($gatewayWebhookId);

        if ($webhook->processing_status === 'processed') {
            return $webhook;
        }

        if (! $webhook->signature_valid) {
            throw new InvalidArgumentException('Cannot reprocess a webhook whose signature was never valid.');
        }

        $driver = $this->drivers->resolve($webhook->driver);
        $event = $driver->parseWebhook($webhook->raw_headers, $webhook->raw_payload);
        $intent = PaymentIntent::query()->where('gateway_reference', $event->gatewayReference)->first();

        if ($intent === null) {
            $webhook->update(['processing_error' => "No payment intent matches gateway reference [{$event->gatewayReference}]."]);

            return $webhook->fresh();
        }

        return $this->transaction(function () use ($webhook, $intent, $event, $processedByUserId, $creditBalanceAccountId, $suspenseAccountId): GatewayWebhook {
            $webhook->update(['intent_id' => $intent->id]);

            if ($event->isSettlement()) {
                $this->settlePayment->execute(new SettleGatewayPaymentData(
                    paymentIntentId: $intent->id,
                    processedByUserId: $processedByUserId,
                    gatewayReference: $event->gatewayReference,
                    amountMinor: $event->amountMinor,
                    feeMinor: $event->feeMinor,
                    creditBalanceAccountId: $creditBalanceAccountId,
                    suspenseAccountId: $suspenseAccountId,
                ));
            } else {
                $intent->update([
                    'status' => $event->status,
                    'failure_code' => $event->failureCode,
                    'failure_message' => $event->failureMessage,
                ]);
            }

            $webhook->update(['processing_status' => 'processed', 'processing_error' => null, 'processed_at' => Carbon::now()]);

            return $webhook->fresh();
        });
    }
}
