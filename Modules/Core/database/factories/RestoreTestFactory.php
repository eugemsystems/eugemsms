<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Backup;
use Modules\Core\Models\RestoreTest;

/**
 * @extends Factory<RestoreTest>
 */
class RestoreTestFactory extends Factory
{
    protected $model = RestoreTest::class;

    public function definition(): array
    {
        return [
            'backup_id' => Backup::factory(),
            'status' => 'passed',
            'target_environment' => 'isolated',
            'checks_performed' => ['row_counts' => []],
            'duration_seconds' => fake()->numberBetween(5, 300),
            'tested_at' => now(),
        ];
    }
}
