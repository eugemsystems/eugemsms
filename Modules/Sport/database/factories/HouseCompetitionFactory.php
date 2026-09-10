<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Sport\Models\HouseCompetition;

/**
 * @extends Factory<HouseCompetition>
 */
class HouseCompetitionFactory extends Factory
{
    protected $model = HouseCompetition::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'name' => 'Inter-House Athletics',
            'competition_type' => 'sport',
            'points_scheme' => [1 => 10, 2 => 7, 3 => 5, 4 => 3],
            'weight' => 1,
            'status' => 'scheduled',
        ];
    }
}
