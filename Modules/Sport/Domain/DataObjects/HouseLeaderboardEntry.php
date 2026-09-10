<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class HouseLeaderboardEntry
{
    /**
     * @param  array<string, float>  $bySource  source_type => total points
     */
    public function __construct(
        public int $houseId,
        public string $houseName,
        public float $totalPoints,
        public array $bySource,
    ) {}
}
