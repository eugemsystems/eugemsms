<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Wallet\Models\SpendPoint;

/**
 * @extends Factory<SpendPoint>
 */
class SpendPointFactory extends Factory
{
    protected $model = SpendPoint::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'TUCK-'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Tuckshop',
            'point_type' => 'tuckshop',
            'income_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id']])->id,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'is_fiscalisable' => true,
            'is_active' => true,
        ];
    }
}
