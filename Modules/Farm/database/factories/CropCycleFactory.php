<?php

declare(strict_types=1);

namespace Modules\Farm\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\ProductionUnit;

/**
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
{
    protected $model = CropCycle::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'production_unit_id' => fn (array $attributes): int => ProductionUnit::factory()->create(['school_id' => $attributes['school_id']])->id,
            'field_id' => fn (array $attributes): int => FarmField::factory()->create([
                'school_id' => $attributes['school_id'], 'production_unit_id' => $attributes['production_unit_id'],
            ])->id,
            'cycle_reference' => 'CYC-'.fake()->unique()->numberBetween(1000, 9999),
            'crop' => 'Maize',
            'season' => 'summer',
            'area_planted_hectares' => 2,
            'currency' => 'USD',
            'status' => 'planned',
        ];
    }
}
