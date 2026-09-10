<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\IngestGatewayWebhookData;
use Modules\Finance\Domain\DataObjects\SettleGatewayPaymentData;
use Modules\Finance\Domain\Events\InvalidWebhookSignatureReceived;
use Modules\Finance\Domain\Support\PaymentGatewayDriverRegistry;
use Modules\Finance\Models\GatewayWebhook;
use Modules\Finance\Models\PaymentIntent;

/**
 * ACT-IngestGatewayWebhook (Book B FIN-05 §6/BR-FIN-05-003/004/005/006
 * (AC-FIN-05-002/003)). The raw payload is stored append-only before
 * anything else happens — verbatim, whatever the outcome, because when
 * a gateway disputes what it sent, this row is what settles it.
 * Replay protection is `UNIQUE (driver, payload_hash)`, checked before
 * insert rather than relying on catching the constraint violation, so
 * a genuine duplicate is recorded cleanly rather than as a DB error.
 */
final class IngestGatewayWebhookAction extends Action
{
    public function __construct(
        private readonly PaymentGatewayDriverRegistry $drivers,
        private readonly SettleGatewayPaymentAction $settlePayment,
    ) {}

    public function execute(IngestGatewayWebhookData $data): GatewayWebhook
    {
        $driver = $this->drivers->resolve($data->driver);
        $payloadHash = hash('sha256', $data->body);

        // BR-FIN-05-004: replay protection is `UNIQUE (driver, payload_hash)`
        // — an identical redelivery never becomes a second row. The
        // existing row already fully describes it; nothing further to
        // record or process (BR-FIN-05-006's exactly-one-receipt
        // guarantee for this driver+payload combination).
        $duplicate = GatewayWebhook::query()
            ->where('driver', $data->driver)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($duplicate !== null) {
            return $duplicate;
        }

        $signatureValid = $driver->verifyWebhook($data->headers, $data->body);

        return $this->transaction(function () use ($data, $driver, $payloadHash, $signatureValid): GatewayWebhook {
            $webhook = GatewayWebhook::create([
                'driver' => $data->driver,
                'raw_headers' => $data->headers,
                'raw_payload' => $data->body,
                'payload_hash' => $payloadHash,
                'signature_valid' => $signatureValid,
                'processing_status' => 'received',
                'received_at' => Carbon::now(),
            ]);

            if (! $signatureValid) {
                $webhook->update(['processing_status' => 'failed', 'processing_error' => 'Invalid webhook signature.']);
                event(new InvalidWebhookSignatureReceived($webhook));

                return $webhook;
            }

            $event = $driver->parseWebhook($data->headers, $data->body);

            $intent = PaymentIntent::query()->where('gateway_reference', $event->gatewayReference)->first();

            if ($intent === null) {
                $webhook->update(['processing_status' => 'failed', 'processing_error' => "No payment intent matches gateway reference [{$event->gatewayReference}]."]);

                return $webhook;
            }

            $webhook->update(['intent_id' => $intent->id]);

            if ($event->isSettlement()) {
                $this->settlePayment->execute(new SettleGatewayPaymentData(
                    paymentIntentId: $intent->id,
                    processedByUserId: $data->processedByUserId,
                    gatewayReference: $event->gatewayReference,
                    amountMinor: $event->amountMinor,
                    feeMinor: $event->feeMinor,
                    creditBalanceAccountId: $data->creditBalanceAccountId,
                    suspenseAccountId: $data->suspenseAccountId,
                ));
            } else {
                $intent->update([
                    'status' => $event->status,
                    'failure_code' => $event->failureCode,
                    'failure_message' => $event->failureMessage,
                ]);
            }

            $webhook->update(['processing_status' => 'processed', 'processed_at' => Carbon::now()]);

            return $webhook;
        });
    }
}
