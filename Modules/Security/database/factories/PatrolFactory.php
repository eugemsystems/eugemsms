<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolRoute;

/**
 * @extends Factory<Patrol>
 */
class PatrolFactory extends Factory
{
    protected $model = Patrol::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'patrol_route_id' => fn (array $attributes): int => PatrolRoute::factory()->create(['school_id' => $attributes['school_id']])->id,
            'guard_staff_id' => fn (array $attributes): int => Staff::factory()->create(['school_id' => $attributes['school_id']])->id,
            'scheduled_at' => now(),
            'checkpoints_expected' => 4,
            'checkpoints_scanned' => 0,
            'status' => 'scheduled',
        ];
    }
}
