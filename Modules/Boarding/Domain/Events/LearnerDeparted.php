<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\Exeat;

final class LearnerDeparted
{
    public function __construct(
        public readonly Exeat $exeat,
    ) {}
}
