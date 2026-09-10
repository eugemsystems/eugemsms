<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Modules\Comms\Domain\Contracts\MessagingGatewayDriver;
use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\MessagingWebhookEvent;
use Modules\Comms\Models\MessageGateway;
use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;
use Modules\Core\Domain\Support\SchoolContext;

/**
 * Book I COM-01 §2/BR-COM-01-013. The push PAYLOAD deliberately never
 * carries `$subject`/`$body` — only enough to route the tap. The full
 * content is what `Notification.body` already holds, fetched by the
 * app from the API on open; this driver's "payload" (simulated —
 * there is no real FCM/APNs call in this pass) is a bare presence
 * signal, by construction incapable of leaking the message content
 * through a push transport.
 */
final readonly class FakePushGatewayDriver implements MessagingGatewayDriver, NotificationChannelDriver
{
    public function channel(): string
    {
        return 'push';
    }

    public function send(string $address, ?string $subject, string $body): ChannelSendResult
    {
        $schoolId = SchoolContext::currentId();

        if ($schoolId === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_school_context', errorMessage: 'No school context is set.');
        }

        $gateway = $this->resolveActiveGateway($schoolId);

        if ($gateway === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_gateway_configured', errorMessage: 'No active, healthy push gateway is configured for this school.');
        }

        // BR-COM-01-013: the simulated wire payload would be just a
        // routing token (e.g. ['token' => $address, 'notice' =>
        // 'new_notification']) — never $subject/$body.
        return new ChannelSendResult(succeeded: true, providerMessageId: 'push-'.uniqid());
    }

    private function resolveActiveGateway(int $schoolId): ?MessageGateway
    {
        return MessageGateway::where('school_id', $schoolId)
            ->where('channel', 'push')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', '!=', 'down'))
            ->orderBy('priority')
            ->first();
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return true;
    }

    public function parseWebhook(array $headers, string $body): MessagingWebhookEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?? [];

        return new MessagingWebhookEvent(
            eventType: (string) ($payload['event'] ?? 'delivered'),
            providerMessageId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
        );
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus('up');
    }
}
