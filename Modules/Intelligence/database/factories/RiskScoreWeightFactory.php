<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\RiskScoreWeight;

/**
 * @extends Factory<RiskScoreWeight>
 */
class RiskScoreWeightFactory extends Factory
{
    protected $model = RiskScoreWeight::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'indicator_key' => 'attendance_decline',
            'weight' => 30,
            'is_enabled' => true,
        ];
    }
}
