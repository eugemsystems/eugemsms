<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\People\Models\Student;
use Modules\Wallet\Models\StudentWallet;

/**
 * @extends Factory<StudentWallet>
 */
class StudentWalletFactory extends Factory
{
    protected $model = StudentWallet::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'balance_minor' => 0,
            'currency' => 'USD',
            'liability_account_id' => fn (array $attributes): int => Account::factory()->create(['school_id' => $attributes['school_id']])->id,
            'status' => 'active',
        ];
    }
}
