<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\EscalationProfile;
use Modules\Core\Models\School;

/**
 * @extends Factory<EscalationProfile>
 */
class EscalationProfileFactory extends Factory
{
    protected $model = EscalationProfile::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Standard Missing Learner Ladder',
            'is_default' => true,
        ];
    }
}
