<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Modules\Comms\Domain\Contracts\MessagingGatewayDriver;
use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\MessagingWebhookEvent;
use Modules\Comms\Models\MessageConversation;
use Modules\Comms\Models\MessageGateway;
use Modules\Comms\Models\WhatsAppBusinessAccount;
use Modules\Comms\Models\WhatsAppTemplate;
use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\DataObjects\Notifications\ChannelSendResult;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * Book I COM-01 §3 ⭐/BR-COM-01-003/005/007 (AC-COM-01-001/002/004).
 * Inside the 24-hour session window (`MessageConversation::isSessionOpen()`),
 * free-form text sends as-is. Outside it, ONLY an approved template
 * may send — refused before reaching the (fake) provider otherwise,
 * returning `succeeded: false` so `DispatchNotificationAction`'s own
 * existing channel-fallback loop (unchanged, already built) moves on
 * to the next configured channel automatically (BR-COM-01-005,
 * AC-COM-01-002). See `WhatsAppTemplate`'s own migration docblock for
 * why template selection is NOT keyed by `notification_key`: Book A's
 * real `send()` contract carries no notification key to match on.
 */
final readonly class FakeWhatsAppGatewayDriver implements MessagingGatewayDriver, NotificationChannelDriver
{
    public function __construct(
        private SettingResolver $settings,
    ) {}

    public function channel(): string
    {
        return 'whatsapp';
    }

    public function send(string $address, ?string $subject, string $body): ChannelSendResult
    {
        $schoolId = SchoolContext::currentId();

        if ($schoolId === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_school_context', errorMessage: 'No school context is set.');
        }

        $gateway = $this->resolveActiveGateway($schoolId);

        if ($gateway === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_gateway_configured', errorMessage: 'No active, healthy WhatsApp gateway is configured for this school.');
        }

        $waba = WhatsAppBusinessAccount::where('school_id', $schoolId)->where('gateway_id', $gateway->id)->first();

        if ($waba === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_waba_configured', errorMessage: 'No WhatsApp Business Account is configured for this gateway.');
        }

        $pauseThreshold = (string) $this->settings->get('comms.whatsapp_quality_pause_threshold', new ScopeChain(schoolId: $schoolId));

        if ($waba->quality_rating === $pauseThreshold) {
            return new ChannelSendResult(succeeded: false, errorCode: 'quality_rating_paused', errorMessage: "WhatsApp Business Account quality rating has reached the pause threshold ({$pauseThreshold}).");
        }

        $conversation = MessageConversation::where('school_id', $schoolId)
            ->where('waba_id', $waba->id)
            ->where('contact_phone', $address)
            ->first();

        if ($conversation !== null && $conversation->isSessionOpen()) {
            return new ChannelSendResult(succeeded: true, providerMessageId: 'wa-session-'.uniqid());
        }

        $template = WhatsAppTemplate::where('school_id', $schoolId)
            ->where('waba_id', $waba->id)
            ->where('review_status', 'approved')
            ->orderByDesc('approved_at')
            ->first();

        if ($template === null) {
            return new ChannelSendResult(succeeded: false, errorCode: 'no_approved_template', errorMessage: 'The WhatsApp session window is closed and no approved template is available.');
        }

        // $template->fillWith($body) is the simulated wire payload — no
        // real Cloud API call exists in this pass to send it through.
        return new ChannelSendResult(succeeded: true, providerMessageId: 'wa-template-'.uniqid());
    }

    private function resolveActiveGateway(int $schoolId): ?MessageGateway
    {
        return MessageGateway::where('school_id', $schoolId)
            ->where('channel', 'whatsapp')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('health_status')->orWhere('health_status', '!=', 'down'))
            ->orderBy('priority')
            ->first();
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return ($headers['X-Hub-Signature-256'] ?? null) !== null;
    }

    public function parseWebhook(array $headers, string $body): MessagingWebhookEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?? [];

        return new MessagingWebhookEvent(
            eventType: (string) ($payload['event'] ?? 'delivered'),
            providerMessageId: isset($payload['message_id']) ? (string) $payload['message_id'] : null,
            status: isset($payload['status']) ? (string) $payload['status'] : null,
            fromAddress: isset($payload['from']) ? (string) $payload['from'] : null,
            body: isset($payload['text']) ? (string) $payload['text'] : null,
        );
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus('up');
    }
}
