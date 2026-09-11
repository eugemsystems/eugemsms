<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class IssueTenantInvoiceData
{
    /**
     * @param  array<int, array{description: string, amount_minor: int}>|null  $lineItems
     */
    public function __construct(
        public int $subscriptionId,
        public string $periodMonth,
        public ?array $lineItems = null,
        public ?int $dueInDays = null,
    ) {}
}
