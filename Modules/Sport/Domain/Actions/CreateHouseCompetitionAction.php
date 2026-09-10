<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\CreateHouseCompetitionData;
use Modules\Sport\Models\HouseCompetition;

/**
 * ACT-CreateHouseCompetition (Book H2 OPS-07 §2).
 */
final class CreateHouseCompetitionAction extends Action
{
    public function execute(CreateHouseCompetitionData $data): HouseCompetition
    {
        return $this->transaction(fn (): HouseCompetition => HouseCompetition::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'name' => $data->name,
            'competition_type' => $data->competitionType,
            'held_on' => $data->heldOn?->toDateString(),
            'points_scheme' => $data->pointsScheme,
            'weight' => $data->weight,
            'status' => 'scheduled',
        ]));
    }
}
