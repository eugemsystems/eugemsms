<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Models\School;

/**
 * @extends Factory<MovementCheckpoint>
 */
class MovementCheckpointFactory extends Factory
{
    protected $model = MovementCheckpoint::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'MAIN_GATE',
            'name' => 'Main Gate',
            'checkpoint_type' => 'gate',
            'is_boundary' => true,
            'is_active' => true,
        ];
    }
}
