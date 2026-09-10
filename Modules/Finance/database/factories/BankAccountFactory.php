<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\BankAccount;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'gl_account_id' => Account::factory()->for($school)->create()->id,
            'bank_name' => 'CBZ Bank',
            'account_name' => 'School Fees Account',
            'account_number' => fake()->unique()->numerify('##########'),
            'currency' => 'USD',
            'account_type' => 'current',
            'is_active' => true,
        ];
    }
}
