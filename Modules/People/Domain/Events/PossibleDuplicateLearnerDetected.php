<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Domain\DataObjects\DuplicateCandidate;

final class PossibleDuplicateLearnerDetected
{
    /**
     * @param  array<int, DuplicateCandidate>  $candidates
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly array $candidates,
    ) {}
}
