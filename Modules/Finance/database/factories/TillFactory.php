<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Till;

/**
 * @extends Factory<Till>
 */
class TillFactory extends Factory
{
    protected $model = Till::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'code' => 'TILL_'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'cash_account_id' => Account::factory()->for($school)->create()->id,
            'accepted_currencies' => ['USD', 'ZWG'],
            'accepted_tenders' => ['cash', 'bank_transfer', 'ecocash'],
            'is_active' => true,
        ];
    }
}
