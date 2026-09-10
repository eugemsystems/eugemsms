<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class IngestMeetingWebhookData
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public string $provider,
        public array $headers,
        public string $body,
    ) {}
}
