<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'MAIN',
            'name' => 'Main Store',
            'store_type' => 'main',
            // Closures, not nested factories: a nested factory tied to its
            // own `for($school)` call would be evaluated against a fresh,
            // separate School — unrelated to whatever school_id a caller's
            // own ->for($school) override ends up setting above. Reading
            // $attributes['school_id'] here sees that final, merged value.
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'inventory_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id'], 'is_postable' => true])->id,
            'default_expense_account_id' => fn (array $attributes): int => Account::factory()->expense()->create(['school_id' => $attributes['school_id']])->id,
            'costing_method' => 'fifo',
            'requires_issue_approval' => false,
            'allows_negative_stock' => false,
            'is_active' => true,
        ];
    }

    public function kitchen(): self
    {
        return $this->state(fn (): array => ['code' => 'KITCHEN', 'name' => 'Kitchen Store', 'store_type' => 'kitchen']);
    }
}
