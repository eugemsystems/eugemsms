<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Backup;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        return [
            'type' => 'database',
            'scope' => 'system',
            'scope_id' => null,
            'disk' => 'backups',
            'path' => 'backups/'.fake()->uuid().'.enc',
            'size_bytes' => fake()->numberBetween(1000, 500000),
            'checksum' => hash('sha256', fake()->uuid()),
            'is_encrypted' => true,
            'status' => 'completed',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'triggered_by' => 'schedule',
        ];
    }
}
