<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\WarehouseSnapshot;

/**
 * @extends Factory<WarehouseSnapshot>
 */
class WarehouseSnapshotFactory extends Factory
{
    protected $model = WarehouseSnapshot::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'entity_key' => 'student',
            'snapshot_date' => now()->toDateString(),
            'row_count' => 100,
            'rebuilt_at' => now(),
            'duration_ms' => 500,
        ];
    }
}
