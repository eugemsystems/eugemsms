<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\ScriptCustodyLogEntry;

final class ScriptDiscrepancyDetected
{
    public function __construct(
        public readonly ScriptBatch $batch,
        public readonly ScriptCustodyLogEntry $entry,
    ) {}
}
