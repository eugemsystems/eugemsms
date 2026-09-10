<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Sport\Models\Activity;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => strtoupper(fake()->unique()->lexify('????')),
            'name' => fake()->unique()->words(2, true),
            'activity_type' => 'sport',
            'season' => 'term_1',
            'gender_scope' => 'both',
            'requires_medical_clearance' => false,
            'requires_guardian_consent' => true,
            'is_active' => true,
        ];
    }
}
