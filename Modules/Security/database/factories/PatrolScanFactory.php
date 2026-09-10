<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Models\School;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolScan;

/**
 * @extends Factory<PatrolScan>
 */
class PatrolScanFactory extends Factory
{
    protected $model = PatrolScan::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'patrol_id' => fn (array $attributes): int => Patrol::factory()->create(['school_id' => $attributes['school_id']])->id,
            'checkpoint_id' => fn (array $attributes): int => MovementCheckpoint::factory()->create(['school_id' => $attributes['school_id']])->id,
            'scanned_at' => now(),
            'method' => 'qr',
        ];
    }
}
