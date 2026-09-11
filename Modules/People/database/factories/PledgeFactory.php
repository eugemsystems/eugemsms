<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Pledge;

/**
 * @extends Factory<Pledge>
 */
class PledgeFactory extends Factory
{
    protected $model = Pledge::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'donor_name' => 'Jane Alumna',
            'donor_type' => 'alumnus',
            'pledged_amount_minor' => 500_000,
            'currency' => 'USD',
            'paid_to_date_minor' => 0,
            'is_anonymous' => false,
            'status' => 'pledged',
        ];
    }
}
