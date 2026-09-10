<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\Detention;

final class DetentionScheduled
{
    public function __construct(
        public readonly Detention $detention,
    ) {}
}
