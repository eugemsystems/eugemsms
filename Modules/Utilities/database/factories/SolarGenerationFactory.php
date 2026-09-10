<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\SolarGeneration;
use Modules\Utilities\Models\SolarInstallation;

/**
 * @extends Factory<SolarGeneration>
 */
class SolarGenerationFactory extends Factory
{
    protected $model = SolarGeneration::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'installation_id' => fn (array $attributes): int => SolarInstallation::factory()->create(['school_id' => $attributes['school_id']])->id,
            'record_date' => now()->toDateString(),
            'kwh_generated' => 120,
            'reading_method' => 'manual',
        ];
    }
}
