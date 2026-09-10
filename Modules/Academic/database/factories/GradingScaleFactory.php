<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\GradingScale;
use Modules\Core\Models\School;

/**
 * @extends Factory<GradingScale>
 */
class GradingScaleFactory extends Factory
{
    protected $model = GradingScale::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('SCALE????')),
            'name' => 'Percentage Scale',
            'scale_type' => 'percentage',
            'lower_is_better' => false,
            'is_active' => true,
        ];
    }
}
