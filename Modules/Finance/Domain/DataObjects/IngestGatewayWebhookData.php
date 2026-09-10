<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class IngestGatewayWebhookData
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public function __construct(
        public string $driver,
        public array $headers,
        public string $body,
        public int $processedByUserId,
        public ?int $creditBalanceAccountId = null,
        public ?int $suspenseAccountId = null,
    ) {}
}
