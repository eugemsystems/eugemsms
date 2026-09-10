<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\Team;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'activity_id' => fn (array $attributes): int => Activity::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => '1st XI',
            'is_active' => true,
        ];
    }
}
