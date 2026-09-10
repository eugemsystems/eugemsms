<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Models\School;

/**
 * @extends Factory<RollCallPoint>
 */
class RollCallPointFactory extends Factory
{
    protected $model = RollCallPoint::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'LIGHTS_OUT',
            'name' => 'Lights Out',
            'scheduled_time' => '21:00:00',
            'applies_on_days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
            'applies_in_term_only' => true,
            'grace_minutes' => 10,
            'is_mandatory' => true,
            'is_active' => true,
        ];
    }
}
