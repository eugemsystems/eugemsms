<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AllocateExamSeatingData
{
    /**
     * @param  array<int, int>  $venueIds  candidate seating venues, in preference order
     */
    public function __construct(
        public int $paperId,
        public array $venueIds,
    ) {}
}
