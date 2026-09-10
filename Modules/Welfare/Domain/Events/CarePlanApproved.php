<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\EmergencyCarePlan;

final class CarePlanApproved
{
    public function __construct(
        public readonly EmergencyCarePlan $plan,
    ) {}
}
