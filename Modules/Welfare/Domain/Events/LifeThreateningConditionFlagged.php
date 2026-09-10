<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\MedicalCondition;

final class LifeThreateningConditionFlagged
{
    public function __construct(
        public readonly MedicalCondition $condition,
    ) {}
}
