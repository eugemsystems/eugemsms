<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\EmergencyDrill;
use Modules\Security\Models\MusterMark;

/**
 * @extends Factory<MusterMark>
 */
class MusterMarkFactory extends Factory
{
    protected $model = MusterMark::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'drill_id' => fn (array $attributes): int => EmergencyDrill::factory()->create(['school_id' => $attributes['school_id']])->id,
            'person_type' => 'student',
            'person_id' => 1,
            'marked_present_at' => now(),
            'marked_by' => User::factory(),
        ];
    }
}
