<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\ScheduleFixtureData;
use Modules\Sport\Models\Fixture;

/**
 * ACT-ScheduleFixture (Book H2 OPS-07 §2).
 */
final class ScheduleFixtureAction extends Action
{
    public function execute(ScheduleFixtureData $data): Fixture
    {
        return $this->transaction(fn (): Fixture => Fixture::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'team_id' => $data->teamId,
            'opponent' => $data->opponent,
            'fixture_type' => $data->fixtureType,
            'venue_type' => $data->venueType,
            'venue_id' => $data->venueId,
            'venue_name' => $data->venueName,
            'fixture_date' => $data->fixtureDate->toDateString(),
            'start_time' => $data->startTime,
            'departure_time' => $data->departureTime,
            'return_time' => $data->returnTime,
            'status' => 'scheduled',
        ]));
    }
}
