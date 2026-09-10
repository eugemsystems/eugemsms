<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class VerifyDietaryRequirementData
{
    public function __construct(
        public int $dietaryRequirementId,
        public int $verifiedByNurseUserId,
    ) {}
}
