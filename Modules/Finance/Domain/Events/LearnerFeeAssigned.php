<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\LearnerFeeAssignment;

final class LearnerFeeAssigned
{
    public function __construct(
        public readonly LearnerFeeAssignment $assignment,
    ) {}
}
