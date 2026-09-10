<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Contracts;

use Modules\Comms\Domain\DataObjects\CreateProviderMeetingData;
use Modules\Comms\Domain\DataObjects\CreateProviderMeetingResult;
use Modules\Comms\Domain\DataObjects\HealthStatus;
use Modules\Comms\Domain\DataObjects\ParsedMeetingWebhookEvent;

/**
 * Book I COM-07 §2/§6/BR-COM-07-006. Mirrors
 * `Modules\Comms\Domain\Contracts\MessagingGatewayDriver`'s own
 * `verifyWebhook`/`parseWebhook`/`healthCheck` shape (COM-01) as
 * closely as possible, plus the two meeting-lifecycle methods this
 * module's own concern adds. Gateway-specific implementations (a real
 * Zoom Server-to-Server OAuth client, a real Google Meet client)
 * belong to a later infrastructure pass, matching the same
 * "single fake driver, not a per-brand registry" boundary already
 * drawn for `FakeSmsGatewayDriver` etc.
 */
interface MeetingProviderDriver
{
    /**
     * @return array<int, string> every provider key this driver serves — e.g. ['zoom', 'google_meet', 'teams']
     */
    public function providers(): array;

    public function createMeeting(CreateProviderMeetingData $data): CreateProviderMeetingResult;

    public function cancelMeeting(string $providerMeetingId): void;

    /**
     * @param  array<string, string>  $headers
     */
    public function verifyWebhook(array $headers, string $body): bool;

    /**
     * @param  array<string, string>  $headers
     */
    public function parseWebhook(array $headers, string $body): ParsedMeetingWebhookEvent;

    public function healthCheck(): HealthStatus;
}
