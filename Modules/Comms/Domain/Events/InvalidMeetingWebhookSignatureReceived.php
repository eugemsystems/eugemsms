<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\MeetingWebhookEvent;

final class InvalidMeetingWebhookSignatureReceived
{
    public function __construct(
        public readonly MeetingWebhookEvent $webhookEvent,
    ) {}
}
