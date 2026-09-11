<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ChaseNonSubmittersData
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public int $assignmentId,
        public array $studentIds,
    ) {}
}
