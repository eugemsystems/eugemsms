<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Events;

use Modules\Utilities\Models\GeneratorRun;

final class GeneratorFuelAnomaly
{
    public function __construct(
        public readonly GeneratorRun $run,
    ) {}
}
