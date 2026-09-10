<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\BudgetVirement;

/**
 * @extends Factory<BudgetVirement>
 */
class BudgetVirementFactory extends Factory
{
    protected $model = BudgetVirement::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'budget_id' => fn (array $attributes): int => Budget::factory()->create(['school_id' => $attributes['school_id']])->id,
            'from_line_id' => fn (array $attributes): int => BudgetLine::factory()->create(['school_id' => $attributes['school_id']])->id,
            'to_line_id' => fn (array $attributes): int => BudgetLine::factory()->create(['school_id' => $attributes['school_id']])->id,
            'amount_minor' => 100000,
            'currency' => 'USD',
            'reason' => 'Reallocating underspend to cover a shortfall.',
            'status' => 'pending',
            'requested_by' => User::factory(),
            'effective_from' => now()->toDateString(),
        ];
    }
}
