<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateContractorWorkerData
{
    public function __construct(
        public int $schoolId,
        public int $contractorId,
        public string $fullName,
        public ?string $idNumber = null,
        public ?CarbonInterface $inductionCompletedOn = null,
        public ?CarbonInterface $policeClearanceOn = null,
        public ?string $badgeNumber = null,
    ) {}
}
