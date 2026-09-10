<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Models\Route;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'code' => 'R'.fake()->unique()->numberBetween(1, 99),
            'name' => 'Northern Suburbs Route',
            'direction' => 'both',
            'capacity' => 65,
            'current_passengers' => 0,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'is_active' => true,
        ];
    }
}
