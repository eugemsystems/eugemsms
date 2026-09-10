<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FeeComponent;

/**
 * @extends Factory<FeeComponent>
 */
class FeeComponentFactory extends Factory
{
    protected $model = FeeComponent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'code' => 'FEE_'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'category' => 'tuition',
            'income_account_id' => Account::factory()->for($school)->income(),
            'debtor_account_id' => Account::factory()->for($school)->controlAccount('student'),
            'default_currency' => 'USD',
            'is_mandatory' => true,
            'is_active' => true,
        ];
    }
}
