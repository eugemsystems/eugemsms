<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Audit;

use Modules\Core\Models\IntegrityCheckRun;

final class IntegrityCheckCompleted
{
    public function __construct(
        public readonly IntegrityCheckRun $run,
    ) {}
}
