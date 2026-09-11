<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateCapitalCampaignData
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public string $purpose,
        public int $targetAmountMinor,
        public string $currency,
        public CarbonInterface $startsOn,
        public int $incomeAccountId,
        public ?CarbonInterface $endsOn = null,
    ) {}
}
