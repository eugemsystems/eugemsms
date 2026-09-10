<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\DietaryRequirement;

final class LifeThreateningAllergyFlagged
{
    public function __construct(
        public readonly DietaryRequirement $requirement,
    ) {}
}
