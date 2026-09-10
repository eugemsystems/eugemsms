<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\BehaviourPointBalance;

final class ConductGradeComputed
{
    public function __construct(
        public readonly BehaviourPointBalance $balance,
    ) {}
}
