<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\MissingLearnerIncident;

final class LearnerMissing
{
    public function __construct(
        public readonly MissingLearnerIncident $incident,
    ) {}
}
