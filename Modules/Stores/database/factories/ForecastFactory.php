<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Stores\Models\Forecast;

/**
 * @extends Factory<Forecast>
 */
class ForecastFactory extends Factory
{
    protected $model = Forecast::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'forecast_type' => 'fee_income',
            'scenario_name' => 'Base',
            'assumptions' => ['collection_rate' => 0.9],
            'projections' => ['term_1' => 1000000, 'term_2' => 1000000, 'term_3' => 1000000],
            'generated_at' => now(),
            'generated_by' => User::factory(),
            'is_baseline' => true,
        ];
    }
}
