<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * Book I COM-07 §3. What `MeetingProviderDriver::parseWebhook()`
 * hands back — deliberately named `Parsed*` rather than
 * `MeetingWebhookEvent` to avoid colliding with
 * `Modules\Comms\Models\MeetingWebhookEvent`, the raw append-only row.
 */
final readonly class ParsedMeetingWebhookEvent
{
    public function __construct(
        public string $eventType,
        public string $providerMeetingId,
        public ?string $participantIdentifier = null,
        public ?CarbonInterface $joinedAt = null,
        public ?CarbonInterface $leftAt = null,
        public ?int $durationSeconds = null,
    ) {}
}
