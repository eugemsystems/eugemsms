<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Contracts\MeetingProviderDriver;
use Modules\Comms\Domain\DataObjects\CreateProviderMeetingData;
use Modules\Comms\Domain\DataObjects\CreateProviderMeetingResult;
use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\ParsedMeetingWebhookEvent;

/**
 * Book I COM-07 §2/§6. A single fake driver serving all three
 * provider strings the spec names (`zoom`/`google_meet`/`teams`) —
 * the same "single fake driver, not a per-brand registry" choice
 * already made for `FakeSmsGatewayDriver`/`FakePaymentGatewayDriver`/
 * `FakeFiscalGatewayDriver`; see each of those for the precedent. A
 * real Zoom Server-to-Server OAuth client belongs to a later
 * infrastructure pass, matching `Modules\Comms\Domain\Contracts\MeetingProviderDriver`'s
 * own docblock.
 */
final class FakeMeetingProviderDriver implements MeetingProviderDriver
{
    public function providers(): array
    {
        return ['zoom', 'google_meet', 'teams'];
    }

    public function createMeeting(CreateProviderMeetingData $data): CreateProviderMeetingResult
    {
        $id = 'fake-'.bin2hex(random_bytes(6));

        return new CreateProviderMeetingResult(
            providerMeetingId: $id,
            joinUrl: "https://meet.example.com/j/{$id}",
            hostUrl: "https://meet.example.com/s/{$id}",
            passcode: (string) random_int(100000, 999999),
        );
    }

    public function cancelMeeting(string $providerMeetingId): void
    {
        // Fake — nothing to actually cancel with a real provider.
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return ($headers['X-Signature'] ?? null) !== null;
    }

    public function parseWebhook(array $headers, string $body): ParsedMeetingWebhookEvent
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($body, true) ?? [];

        return new ParsedMeetingWebhookEvent(
            eventType: (string) ($payload['event'] ?? 'participant.joined'),
            providerMeetingId: (string) ($payload['meeting_id'] ?? ''),
            participantIdentifier: isset($payload['participant']) ? (string) $payload['participant'] : null,
            joinedAt: isset($payload['joined_at']) ? Carbon::parse((string) $payload['joined_at']) : null,
            leftAt: isset($payload['left_at']) ? Carbon::parse((string) $payload['left_at']) : null,
            durationSeconds: isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null,
        );
    }

    public function healthCheck(): HealthStatus
    {
        return new HealthStatus('up');
    }
}
