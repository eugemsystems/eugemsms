<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class IngestTenantGatewayWebhookData
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public string $driverKey,
        public int $invoiceId,
        public array $headers,
        public string $body,
    ) {}
}
