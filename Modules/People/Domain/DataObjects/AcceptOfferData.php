<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class AcceptOfferData
{
    public function __construct(
        public int $applicationId,
        public int $acceptedByUserId,
    ) {}
}
