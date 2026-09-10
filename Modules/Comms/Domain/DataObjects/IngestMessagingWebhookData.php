<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class IngestMessagingWebhookData
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public string $channel,
        public string $driver,
        public array $headers,
        public string $body,
    ) {}
}
