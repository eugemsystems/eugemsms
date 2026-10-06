<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Sponsorship;

/**
 * @extends Factory<Sponsorship>
 */
class SponsorshipFactory extends Factory
{
    protected $model = Sponsorship::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'guardian_id' => Guardian::factory()->for($school), 'name' => 'Diocese Orphan Support', 'sponsorship_type' => 'full', 'starts_on' => now()->toDateString(), 'status' => 'active',
        ];
    }
}
