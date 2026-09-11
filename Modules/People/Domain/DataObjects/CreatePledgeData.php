<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreatePledgeData
{
    /**
     * @param  array<int, array<string, mixed>>|null  $schedule
     */
    public function __construct(
        public int $schoolId,
        public string $donorName,
        public string $donorType,
        public int $pledgedAmountMinor,
        public string $currency,
        public ?int $campaignId = null,
        public ?int $alumnusId = null,
        public ?array $schedule = null,
        public ?string $recognitionTier = null,
        public bool $isAnonymous = false,
    ) {}
}
