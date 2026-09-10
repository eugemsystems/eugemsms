<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class RecordHouseCompetitionResultData
{
    /**
     * @param  array<int, array{house_id: int, place: int}>  $placements
     */
    public function __construct(
        public int $competitionId,
        public array $placements,
        public int $termId,
        public ?int $awardedByUserId = null,
    ) {}
}
