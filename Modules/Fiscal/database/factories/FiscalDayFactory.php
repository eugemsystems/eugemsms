<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * @extends Factory<FiscalDay>
 */
class FiscalDayFactory extends Factory
{
    protected $model = FiscalDay::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'device_id' => fn (array $attributes): int => FiscalDevice::factory()->create(['school_id' => $attributes['school_id']])->id,
            'fiscal_day_number' => fake()->unique()->numberBetween(1, 100000),
            'opened_at' => now(),
            'opened_by' => User::factory(),
            'local_status' => 'open',
        ];
    }
}
