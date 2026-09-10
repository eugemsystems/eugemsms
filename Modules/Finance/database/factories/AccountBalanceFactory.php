<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountBalance;

/**
 * @extends Factory<AccountBalance>
 */
class AccountBalanceFactory extends Factory
{
    protected $model = AccountBalance::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'account_id' => Account::factory(),
            'term_id' => Term::factory(),
            'currency' => 'USD',
            'opening_minor' => 0,
            'debit_minor' => 0,
            'credit_minor' => 0,
            'closing_minor' => 0,
            'line_count' => 0,
            'rebuilt_at' => now(),
        ];
    }
}
