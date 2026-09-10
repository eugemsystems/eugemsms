<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Models\UtilityAccount;

/**
 * @extends Factory<UtilityAccount>
 */
class UtilityAccountFactory extends Factory
{
    protected $model = UtilityAccount::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'utility_type' => 'electricity',
            'provider' => 'ZESA',
            'account_number' => 'ACC-'.fake()->unique()->numberBetween(10000, 99999),
            'billing_mode' => 'prepaid',
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'expense_account_id' => fn (array $attributes): int => Account::factory()->expense()->create(['school_id' => $attributes['school_id']])->id,
            'is_active' => true,
        ];
    }
}
