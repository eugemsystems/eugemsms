<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Models\BursaryEndowment;

/**
 * @extends Factory<BursaryEndowment>
 */
class BursaryEndowmentFactory extends Factory
{
    protected $model = BursaryEndowment::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'donor_name' => 'The Moyo Family',
            'endowment_capital_minor' => 5_000_000,
            'currency' => 'USD',
            'funds_scheme_id' => DiscountScheme::factory()->for($school)->sponsorFunded(),
            'named_recognition' => 'The Moyo Family Bursary',
            'is_anonymous' => false,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
        ];
    }
}
