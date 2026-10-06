<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class EndSponsorshipBeneficiaryData
{
    public function __construct(
        public int $beneficiaryId,
        public string $newStatus = 'ended',
        public ?CarbonInterface $endsOn = null,
    ) {}
}
