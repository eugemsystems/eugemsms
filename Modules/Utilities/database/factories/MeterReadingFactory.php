<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\MeterReading;

/**
 * @extends Factory<MeterReading>
 */
class MeterReadingFactory extends Factory
{
    protected $model = MeterReading::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'meter_id' => fn (array $attributes): int => Meter::factory()->create(['school_id' => $attributes['school_id']])->id,
            'read_on' => now()->toDateString(),
            'reading' => 1000,
            'reading_method' => 'manual',
            'read_by' => User::factory(),
            'is_anomaly' => false,
        ];
    }
}
