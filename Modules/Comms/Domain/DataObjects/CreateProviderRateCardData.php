<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class CreateProviderRateCardData
{
    public function __construct(
        public int $gatewayId,
        public string $destinationPrefix,
        public int $ratePerSegmentMinor,
        public string $currency,
        public string $effectiveFrom,
        public ?int $schoolId = null,
        public ?int $whatsappUtilityRateMinor = null,
        public ?int $whatsappMarketingRateMinor = null,
        public ?string $effectiveTo = null,
    ) {}
}
