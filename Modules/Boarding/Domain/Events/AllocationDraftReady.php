<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Illuminate\Support\Collection;
use Modules\Boarding\Domain\Support\AllocationOutcome;

final class AllocationDraftReady
{
    /**
     * @param  Collection<int, AllocationOutcome>  $outcomes
     */
    public function __construct(
        public readonly int $schoolId,
        public readonly int $termId,
        public readonly Collection $outcomes,
    ) {}
}
