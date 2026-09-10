<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Contracts\MessagingGatewayDriver;
use Modules\Comms\Domain\DataObjects\IngestMessagingWebhookData;
use Modules\Comms\Domain\Events\InvalidMessagingWebhookSignatureReceived;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\MessagingGatewayWebhook;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Registry\NotificationChannelDriverRegistry;
use Modules\Core\Models\Notification;

/**
 * ACT-IngestMessagingWebhook (Book I COM-01 §2/BR-COM-01-010
 * (AC-COM-01-005)). The exact pattern established in `FIN-05` §5
 * (`Modules\Finance\Domain\Actions\IngestGatewayWebhookAction`): the
 * raw payload is stored append-only before anything else happens;
 * replay protection is `UNIQUE (driver, payload_hash)`, checked
 * before insert so a genuine redelivery returns the existing row
 * cleanly rather than racing the DB constraint.
 *
 * **Simplification**: a gateway is resolved by `(driver, is_active)`
 * alone, not by a school-scoped webhook URL/token — this pass has one
 * fake driver per channel, so no real multi-school same-driver
 * ambiguity exists to resolve yet. A real per-provider integration
 * would need the webhook URL or payload itself to carry the school
 * scope; flagged here rather than silently assumed away.
 */
final class IngestMessagingWebhookAction extends Action
{
    public function __construct(
        private readonly RecordInboundWhatsAppMessageAction $recordInboundWhatsAppMessage,
    ) {}

    public function execute(IngestMessagingWebhookData $data): MessagingGatewayWebhook
    {
        $payloadHash = hash('sha256', $data->body);

        $duplicate = MessagingGatewayWebhook::query()
            ->where('driver', $data->driver)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($duplicate !== null) {
            return $duplicate;
        }

        $gateway = MessageGateway::where('driver', $data->driver)->where('is_active', true)->first();
        $resolved = NotificationChannelDriverRegistry::forChannel($data->channel);

        if (! $resolved instanceof MessagingGatewayDriver) {
            return $this->transaction(function () use ($data, $payloadHash, $gateway): MessagingGatewayWebhook {
                $webhook = MessagingGatewayWebhook::create([
                    'school_id' => $gateway?->school_id,
                    'gateway_id' => $gateway?->id,
                    'driver' => $data->driver,
                    'raw_headers' => $data->headers,
                    'raw_payload' => $data->body,
                    'payload_hash' => $payloadHash,
                    'signature_valid' => false,
                    'processing_status' => 'failed',
                    'processing_error' => 'No messaging driver is registered for this channel.',
                    'received_at' => Carbon::now(),
                ]);

                event(new InvalidMessagingWebhookSignatureReceived($webhook));

                return $webhook;
            });
        }

        $driver = $resolved;
        $signatureValid = $driver->verifyWebhook($data->headers, $data->body);

        return $this->transaction(function () use ($data, $driver, $payloadHash, $signatureValid, $gateway): MessagingGatewayWebhook {
            $webhook = MessagingGatewayWebhook::create([
                'school_id' => $gateway?->school_id,
                'gateway_id' => $gateway?->id,
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
                event(new InvalidMessagingWebhookSignatureReceived($webhook));

                return $webhook;
            }

            $event = $driver->parseWebhook($data->headers, $data->body);
            $webhook->update(['event_type' => $event->eventType]);

            if ($event->isDeliveryStatus()) {
                $this->applyDeliveryStatus($webhook, $event->providerMessageId, $event->status, $event->failureCode, $event->failureMessage);
            } elseif ($event->isInboundMessage() && $gateway !== null && $event->fromAddress !== null) {
                $this->recordInbound($gateway, $event->fromAddress);
            }

            $webhook->update(['processing_status' => 'processed', 'processed_at' => Carbon::now()]);

            return $webhook;
        });
    }

    private function applyDeliveryStatus(MessagingGatewayWebhook $webhook, ?string $providerMessageId, ?string $status, ?string $failureCode, ?string $failureMessage): void
    {
        if ($providerMessageId === null) {
            $webhook->update(['processing_status' => 'ignored']);

            return;
        }

        $notification = Notification::where('provider_message_id', $providerMessageId)->first();

        if ($notification === null) {
            $webhook->update(['processing_status' => 'failed', 'processing_error' => "No notification matches provider_message_id [{$providerMessageId}]."]);

            return;
        }

        $webhook->update(['notification_id' => $notification->id]);

        match ($status) {
            'delivered' => $notification->update(['status' => 'delivered', 'delivered_at' => Carbon::now()]),
            'read' => $notification->update(['status' => 'delivered', 'read_at' => Carbon::now()]),
            'failed' => $notification->update(['status' => 'failed', 'failed_at' => Carbon::now(), 'error_code' => $failureCode, 'error_message' => $failureMessage]),
            default => null,
        };
    }

    private function recordInbound(MessageGateway $gateway, string $fromAddress): void
    {
        $waba = WhatsAppBusinessAccount::where('gateway_id', $gateway->id)->first();

        if ($waba === null) {
            return;
        }

        $this->recordInboundWhatsAppMessage->execute($gateway->school_id, $waba->id, $fromAddress);
    }
}
