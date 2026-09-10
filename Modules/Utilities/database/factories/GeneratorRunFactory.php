<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;

/**
 * @extends Factory<GeneratorRun>
 */
class GeneratorRunFactory extends Factory
{
    protected $model = GeneratorRun::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'generator_id' => fn (array $attributes): int => Generator::factory()->create(['school_id' => $attributes['school_id']])->id,
            'run_date' => now()->toDateString(),
            'started_at' => now(),
            'start_hour_meter' => 0,
            'reason' => 'load_shedding',
            'is_anomaly' => false,
        ];
    }
}
