<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class SelectFixtureSquadData
{
    /**
     * @param  array<int, int>  $squadStudentIds
     * @param  array<int, int>  $staffIds
     */
    public function __construct(
        public int $fixtureId,
        public array $squadStudentIds,
        public array $staffIds = [],
    ) {}
}
