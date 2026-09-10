<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Events;

use Modules\Security\Models\Patrol;

final class PatrolIncomplete
{
    public function __construct(
        public readonly Patrol $patrol,
    ) {}
}
