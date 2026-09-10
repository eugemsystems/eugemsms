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
 * Book I COM-01 §2/3. Email has no session window, no per-segment
 * cost, no Meta template approval — the simplest of the four drivers.
 */
final readonly class FakeEmailGatewayDriver implements MessagingGatewayDriver, NotificationChannelDriver
{
    public function channel(): string
    {
        return 'email';
    }

    public function send(string $address, ?string $subject, string $body): ChannelSendResult
    {
        $schoolId = SchoolContext::currentId();

        if ($schoolId === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_school_context', errorMessage: 'No school context is set.');
        }

        $gateway = $this->resolveActiveGateway($schoolId);

        if ($gateway === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_gateway_configured', errorMessage: 'No active, healthy email gateway is configured for this school.');
        }

        return new ChannelSendResult(succeeded: true, providerMessageId: 'email-'.uniqid());
    }

    private function resolveActiveGateway(int $schoolId): ?MessageGateway
    {
        return MessageGateway::where('school_id', $schoolId)
            ->where('channel', 'email')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', '!=', 'down'))
            ->orderBy('priority')
            ->first();
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return ($headers['X-Webhook-Signature'] ?? null) !== null;
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
