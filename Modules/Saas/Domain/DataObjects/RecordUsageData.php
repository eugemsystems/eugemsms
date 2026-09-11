<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\DataObjects;

final readonly class RecordUsageData
{
    public function __construct(
        public int $tenantId,
        public string $metric,
        public float $usageValue,
        public ?string $periodMonth = null,
    ) {}
}
