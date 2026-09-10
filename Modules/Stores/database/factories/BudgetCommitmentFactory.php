<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Stores\Models\BudgetCommitment;
use Modules\Stores\Models\BudgetLine;

/**
 * @extends Factory<BudgetCommitment>
 */
class BudgetCommitmentFactory extends Factory
{
    protected $model = BudgetCommitment::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'budget_line_id' => fn (array $attributes): int => BudgetLine::factory()->create(['school_id' => $attributes['school_id']])->id,
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'committed_minor' => 2400000,
            'released_minor' => 0,
            'outstanding_minor' => 2400000,
            'currency' => 'USD',
            'committed_at' => now(),
            'status' => 'open',
        ];
    }
}
