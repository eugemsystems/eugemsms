<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RemoveHouseholdMemberData
{
    public function __construct(
        public int $householdId,
        public string $memberType,
        public int $memberId,
        public ?CarbonInterface $leftOn = null,
    ) {}
}
