<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class OfferApplicationData
{
    public function __construct(
        public int $applicationId,
        public int $offeredByUserId,
        public int $offerValidDays = 14,
    ) {}
}
