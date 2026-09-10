<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\WaterReading;
use Modules\Utilities\Models\WaterSource;

/**
 * @extends Factory<WaterReading>
 */
class WaterReadingFactory extends Factory
{
    protected $model = WaterReading::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'water_source_id' => fn (array $attributes): int => WaterSource::factory()->create(['school_id' => $attributes['school_id']])->id,
            'read_on' => now()->toDateString(),
            'storage_level_percent' => 80,
            'read_by' => User::factory(),
        ];
    }
}
