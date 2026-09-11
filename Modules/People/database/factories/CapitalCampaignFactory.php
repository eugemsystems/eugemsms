<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\People\Models\CapitalCampaign;

/**
 * @extends Factory<CapitalCampaign>
 */
class CapitalCampaignFactory extends Factory
{
    protected $model = CapitalCampaign::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'name' => 'New Science Block Appeal',
            'purpose' => 'Fund the construction of a new science block.',
            'target_amount_minor' => 10_000_000,
            'raised_amount_minor' => 0,
            'currency' => 'USD',
            'starts_on' => now()->toDateString(),
            'income_account_id' => Account::factory()->for($school)->income(),
            'status' => 'active',
        ];
    }
}
