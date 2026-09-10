<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\MalpracticeIncident;

final class MalpracticeReported
{
    public function __construct(
        public readonly MalpracticeIncident $incident,
    ) {}
}
