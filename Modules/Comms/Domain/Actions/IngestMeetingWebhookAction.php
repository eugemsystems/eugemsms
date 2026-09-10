<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\DataObjects\IngestMeetingWebhookData;
use Modules\Comms\Domain\Events\InvalidMeetingWebhookSignatureReceived;
use Modules\Comms\Domain\Registry\MeetingProviderDriverRegistry;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\MeetingWebhookEvent;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-IngestMeetingWebhook (Book I COM-07 §6/BR-COM-07-006). The
 * exact pattern established in `FIN-05` (`IngestGatewayWebhookAction`)
 * and `COM-01` (`IngestMessagingWebhookAction`) — see either's own
 * docblock. The raw payload is stored append-only before anything
 * else happens; replay protection is `UNIQUE(provider, payload_hash)`,
 * checked before insert so a genuine redelivery returns the existing
 * row cleanly rather than racing the DB constraint.
 */
final class IngestMeetingWebhookAction extends Action
{
    public function __construct(
        private readonly RecordMeetingAttendanceEventAction $recordAttendanceEvent,
    ) {}

    public function execute(IngestMeetingWebhookData $data): MeetingWebhookEvent
    {
        $payloadHash = hash('sha256', $data->body);

        $duplicate = MeetingWebhookEvent::query()
            ->where('provider', $data->provider)
            ->where('payload_hash', $payloadHash)
            ->first();

        if ($duplicate !== null) {
            return $duplicate;
        }

        $provider = MeetingProvider::where('provider', $data->provider)->where('is_active', true)->first();
        $driver = MeetingProviderDriverRegistry::forProvider($data->provider);

        if ($driver === null) {
            return $this->transaction(function () use ($data, $payloadHash, $provider): MeetingWebhookEvent {
                $webhookEvent = MeetingWebhookEvent::create([
                    'school_id' => $provider?->school_id,
                    'provider' => $data->provider,
                    'raw_headers' => $data->headers,
                    'raw_payload' => $data->body,
                    'payload_hash' => $payloadHash,
                    'signature_valid' => false,
                    'processing_status' => 'failed',
                    'processing_error' => 'No meeting provider driver is registered for this provider.',
                    'received_at' => Carbon::now(),
                ]);

                event(new InvalidMeetingWebhookSignatureReceived($webhookEvent));

                return $webhookEvent;
            });
        }

        $signatureValid = $driver->verifyWebhook($data->headers, $data->body);

        return $this->transaction(function () use ($data, $driver, $payloadHash, $signatureValid, $provider): MeetingWebhookEvent {
            $webhookEvent = MeetingWebhookEvent::create([
                'school_id' => $provider?->school_id,
                'provider' => $data->provider,
                'raw_headers' => $data->headers,
                'raw_payload' => $data->body,
                'payload_hash' => $payloadHash,
                'signature_valid' => $signatureValid,
                'processing_status' => 'received',
                'received_at' => Carbon::now(),
            ]);

            if (! $signatureValid) {
                $webhookEvent->update(['processing_status' => 'failed', 'processing_error' => 'Invalid webhook signature.']);
                event(new InvalidMeetingWebhookSignatureReceived($webhookEvent));

                return $webhookEvent;
            }

            $parsed = $driver->parseWebhook($data->headers, $data->body);
            $webhookEvent->update(['event_type' => $parsed->eventType]);

            $meeting = ScheduledMeeting::where('provider_meeting_id', $parsed->providerMeetingId)->first();

            if ($meeting === null) {
                $webhookEvent->update(['processing_status' => 'failed', 'processing_error' => "No scheduled meeting matches provider_meeting_id [{$parsed->providerMeetingId}]."]);

                return $webhookEvent;
            }

            $webhookEvent->update(['meeting_id' => $meeting->id]);

            if (in_array($parsed->eventType, ['participant.joined', 'participant.left'], true)) {
                $this->recordAttendanceEvent->execute($meeting, $parsed);
            }

            $webhookEvent->update(['processing_status' => 'processed', 'processed_at' => Carbon::now()]);

            return $webhookEvent;
        });
    }
}
