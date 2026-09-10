<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatement;

/**
 * @extends Factory<BankStatement>
 */
class BankStatementFactory extends Factory
{
    protected $model = BankStatement::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'bank_account_id' => BankAccount::factory()->create(['school_id' => $school->id])->id,
            'statement_from' => now()->subDays(7)->toDateString(),
            'statement_to' => now()->toDateString(),
            'opening_balance_minor' => 0,
            'closing_balance_minor' => 0,
            'currency' => 'USD',
            'status' => 'imported',
            'imported_by' => User::factory(),
        ];
    }
}
