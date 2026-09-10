<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\House;
use Modules\Sport\Domain\DataObjects\HouseLeaderboardEntry;
use Modules\Sport\Models\HousePoint;

/**
 * ACT-ComputeHouseLeaderboard (Book H2 OPS-07 §3/BR-OPS-07-009/
 * AC-OPS-07-004). Live from `house_points` — no separate stored
 * standings table, the same "no shadow aggregate" doctrine
 * `Modules\Security`'s `AssembleMusterRollAction` (Book H2 OPS-06)
 * already established for this book. Each source's already-weighted
 * `points` (competition rows are pre-multiplied by the competition's
 * own `weight` at write time; behaviour/inspection rows are weighted
 * by their own listener) are simply summed here — this action applies
 * no further weighting of its own.
 */
final class ComputeHouseLeaderboardAction extends Action
{
    /**
     * @return Collection<int, HouseLeaderboardEntry>
     */
    public function execute(int $schoolId, int $academicYearId): Collection
    {
        $houses = House::where('school_id', $schoolId)->where('is_active', true)->get();

        $points = HousePoint::where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get();

        return $houses
            ->map(function (House $house) use ($points): HouseLeaderboardEntry {
                $forHouse = $points->where('house_id', $house->id);

                $bySource = $forHouse
                    ->groupBy('source_type')
                    ->map(fn (Collection $rows): float => (float) $rows->sum(fn (HousePoint $row): float => (float) $row->points))
                    ->all();

                return new HouseLeaderboardEntry(
                    houseId: $house->id,
                    houseName: $house->name,
                    totalPoints: (float) $forHouse->sum(fn (HousePoint $row): float => (float) $row->points),
                    bySource: $bySource,
                );
            })
            ->sortByDesc(fn (HouseLeaderboardEntry $entry): float => $entry->totalPoints)
            ->values();
    }
}
