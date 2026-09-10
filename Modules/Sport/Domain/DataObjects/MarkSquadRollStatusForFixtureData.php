<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class MarkSquadRollStatusForFixtureData
{
    /**
     * @param  array<int, int>  $rollCallIds  every BRD-02 roll call that falls within the fixture's window
     */
    public function __construct(
        public int $fixtureId,
        public array $rollCallIds,
        public int $markedByUserId,
    ) {}
}
