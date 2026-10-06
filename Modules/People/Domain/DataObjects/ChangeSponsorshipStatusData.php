<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ChangeSponsorshipStatusData
{
    public function __construct(
        public int $sponsorshipId,
        public string $newStatus,
    ) {}
}
