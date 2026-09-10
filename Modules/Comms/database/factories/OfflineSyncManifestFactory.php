<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\OfflineSyncManifest;

/**
 * @extends Factory<OfflineSyncManifest>
 */
class OfflineSyncManifestFactory extends Factory
{
    protected $model = OfflineSyncManifest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'data_category' => 'timetable',
            'last_synced_at' => null,
            'sync_token' => null,
            'cache_ttl_hours' => 24,
        ];
    }
}
