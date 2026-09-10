<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'budget_id' => fn (array $attributes): int => Budget::factory()->create(['school_id' => $attributes['school_id']])->id,
            'account_id' => fn (array $attributes): int => Account::factory()->expense()->create(['school_id' => $attributes['school_id']])->id,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'annual_amount_minor' => 18000000,
            'currency' => 'USD',
            'committed_minor' => 0,
            'actual_minor' => 0,
            'available_minor' => 18000000,
            'is_locked' => false,
        ];
    }
}
