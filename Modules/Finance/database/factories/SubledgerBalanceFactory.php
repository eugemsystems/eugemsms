<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\SubledgerBalance;

/**
 * @extends Factory<SubledgerBalance>
 */
class SubledgerBalanceFactory extends Factory
{
    protected $model = SubledgerBalance::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'subledger_type' => 'learner',
            'subledger_id' => fake()->numberBetween(1, 1000),
            'account_id' => Account::factory(),
            'term_id' => Term::factory(),
            'currency' => 'USD',
            'opening_minor' => 0,
            'debit_minor' => 0,
            'credit_minor' => 0,
            'closing_minor' => 0,
            'rebuilt_at' => now(),
        ];
    }
}
