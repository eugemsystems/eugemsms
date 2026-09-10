<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\RecordHouseCompetitionResultData;
use Modules\Sport\Models\HouseCompetition;
use Modules\Sport\Models\HousePoint;

/**
 * ACT-RecordHouseCompetitionResult (Book H2 OPS-07 §3/BR-OPS-07-008).
 * Each placed house's points come straight from the competition's own
 * `points_scheme` (keyed by place), multiplied by its `weight` — the
 * single source of truth this book's own `house_points.source_type =
 * 'competition'` rows read from, no separate configuration.
 */
final class RecordHouseCompetitionResultAction extends Action
{
    /**
     * @return Collection<int, HousePoint>
     */
    public function execute(RecordHouseCompetitionResultData $data): Collection
    {
        $competition = HouseCompetition::findOrFail($data->competitionId);

        return $this->transaction(function () use ($competition, $data): Collection {
            $points = new Collection;

            foreach ($data->placements as $placement) {
                $rawPoints = (float) ($competition->points_scheme[$placement['place']] ?? 0);
                $weightedPoints = $rawPoints * (float) $competition->weight;

                $points->push(HousePoint::create([
                    'school_id' => $competition->school_id,
                    'academic_year_id' => $competition->academic_year_id,
                    'term_id' => $data->termId,
                    'house_id' => $placement['house_id'],
                    'competition_id' => $competition->id,
                    'source_type' => 'competition',
                    'source_id' => $competition->id,
                    'points' => $weightedPoints,
                    'reason' => "{$competition->name} — place {$placement['place']}",
                    'awarded_at' => Carbon::now(),
                    'awarded_by' => $data->awardedByUserId,
                ]));
            }

            $competition->update(['status' => 'completed']);

            return $points;
        });
    }
}
