<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Sport\Models\Fixture;
use Modules\Sport\Models\Team;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    protected $model = Fixture::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => function (array $attributes): int {
                $year = AcademicYear::factory()->create(['school_id' => $attributes['school_id']]);

                return Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $year->id])->id;
            },
            'team_id' => fn (array $attributes): int => Team::factory()->create(['school_id' => $attributes['school_id']])->id,
            'opponent' => 'Visiting School',
            'fixture_type' => 'friendly',
            'venue_type' => 'home',
            'fixture_date' => now()->addWeek()->toDateString(),
            'status' => 'scheduled',
        ];
    }
}
