<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\DepreciationRun;

final class DepreciationPosted
{
    public function __construct(
        public readonly DepreciationRun $run,
    ) {}
}
