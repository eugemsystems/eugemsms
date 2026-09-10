<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

/**
 * Book E ACA-03 §3 ⭐. One of the four unconditionally-hard clash
 * levels — teacher, venue, class, learner — never a `timetable_constraints`
 * soft/hard toggle. `sharedLearnerIds` is empty for the first three
 * levels.
 */
final readonly class TimetableClash
{
    /**
     * @param  array<int, int>  $sharedLearnerIds
     */
    public function __construct(
        public string $level,
        public int $slotIdA,
        public int $slotIdB,
        public array $sharedLearnerIds = [],
    ) {}
}
