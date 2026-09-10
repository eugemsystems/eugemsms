<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Contracts\MessagingGatewayDriver;
use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\MessagingWebhookEvent;
use Modules\Comms\Domain\Events\SegmentCountExceededExpected;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\MessageSegment;
use Modules\Comms\Models\SenderId;
use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\Notification;

/**
 * Book I COM-01 §3/§4 ⭐. Real per-provider drivers (Africa's
 * Talking, BulkSMS Zimbabwe, Econet Bulk) each need live credentials
 * and a real HTTP integration this pass doesn't have — deterministic
 * and simulate-able instead, the same "single fake driver, not a
 * per-brand registry" choice this codebase already made for
 * `Modules\Fiscal\Domain\Support\FakeFiscalGatewayDriver` and
 * `Modules\Finance\Domain\Support\FakePaymentGatewayDriver`.
 *
 * **The `send()` interface constraint.** Book A's real
 * `NotificationChannelDriver::send()` takes only `(address, subject,
 * body)` — no notification id. The `Notification` row already exists
 * (created by `DispatchNotificationAction` before `send()` runs), so
 * this driver recovers it by matching `(recipient_address, body,
 * status = 'sending')`, safe because that triple is unique to the
 * just-created row for the duration of one dispatch call. Recovering
 * it lets this driver both persist `message_segments` AND overwrite
 * `Notification.body` with the NORMALISED text — BR-COM-01-009 says
 * normalisation applies "to the rendered text, not silently to the
 * stored template": the template (`NotificationTemplate.body`) is
 * never touched by this driver; the per-send `Notification.body` is
 * updated to reflect what was actually transmitted.
 */
final readonly class FakeSmsGatewayDriver implements MessagingGatewayDriver, NotificationChannelDriver
{
    public function __construct(
        private SmsSegmentCalculator $calculator,
    ) {}

    public function channel(): string
    {
        return 'sms';
    }

    public function send(string $address, ?string $subject, string $body): ChannelSendResult
    {
        $schoolId = SchoolContext::currentId();

        if ($schoolId === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_school_context', errorMessage: 'No school context is set.');
        }

        $gateway = $this->resolveActiveGateway($schoolId);

        if ($gateway === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_gateway_configured', errorMessage: 'No active, healthy SMS gateway is configured for this school.');
        }

        $result = $this->calculator->calculate($body);
        $rateCardMinor = 2;
        $costMinor = $rateCardMinor * $result->segmentCount;

        $notification = Notification::where('recipient_address', $address)
            ->where('body', $body)
            ->where('status', 'sending')
            ->latest('id')
            ->first();

        if ($notification !== null) {
            $notification->update(['body' => $result->normalizedBody]);

            MessageSegment::create([
                'school_id' => $schoolId,
                'notification_id' => $notification->id,
                'encoding' => $result->encoding,
                'character_count' => $result->characterCount,
                'segment_count' => $result->segmentCount,
                'cost_minor' => $costMinor,
                'currency' => 'USD',
            ]);
        }

        if ($result->encoding === 'ucs2' && $result->segmentCount > 1 && $result->characterCount <= 160) {
            event(new SegmentCountExceededExpected($schoolId, $result->characterCount, $result->segmentCount));
        }

        $this->resolveSenderId($schoolId, $gateway->id);

        return new ChannelSendResult(
            succeeded: true,
            providerMessageId: 'sms-'.uniqid(),
            costMinor: $costMinor,
        );
    }

    /**
     * BR-COM-01-002/011: highest-priority ACTIVE, HEALTHY gateway.
     */
    private function resolveActiveGateway(int $schoolId): ?MessageGateway
    {
        return MessageGateway::where('school_id', $schoolId)
            ->where('channel', 'sms')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', '!=', 'down'))
            ->orderBy('priority')
            ->first();
    }

    /**
     * BR-COM-01-008: a pending/rejected sender ID never silently
     * sends under itself — the send still succeeds (a generic sender
     * ID is always available), it simply isn't attributed to that row.
     */
    private function resolveSenderId(int $schoolId, int $gatewayId): ?SenderId
    {
        return SenderId::where('school_id', $schoolId)
            ->where('gateway_id', $gatewayId)
            ->where('status', 'approved')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', Carbon::now()->toDateString()))
            ->first();
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return ($headers['X-Signature'] ?? null) !== null;
    }

    public function parseWebhook(array $headers, string $body): MessagingWebhookEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?? [];

        return new MessagingWebhookEvent(
            eventType: (string) ($payload['event'] ?? 'delivered'),
            providerMessageId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            status: isset($payload['status']) ? (string) $payload['status'] : null,
            failureCode: isset($payload['failure_code']) ? (string) $payload['failure_code'] : null,
        );
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus('up');
    }
}
