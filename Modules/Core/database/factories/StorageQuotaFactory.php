<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\StorageQuota;

/**
 * @extends Factory<StorageQuota>
 */
class StorageQuotaFactory extends Factory
{
    protected $model = StorageQuota::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'quota_bytes' => 10 * 1024 * 1024 * 1024,
            'used_bytes' => 0,
            'warn_at_percent' => 85,
        ];
    }
}
