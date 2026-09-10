<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\AssetInsurance;

/**
 * @extends Factory<AssetInsurance>
 */
class AssetInsuranceFactory extends Factory
{
    protected $model = AssetInsurance::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'policy_number' => 'POL-'.fake()->unique()->numberBetween(10000, 99999),
            'insurer' => 'Old Mutual Insurance',
            'policy_type' => 'all_risk',
            'sum_insured_minor' => 5000000,
            'currency' => 'USD',
            'premium_minor' => 50000,
            'starts_on' => now()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'status' => 'active',
        ];
    }
}
