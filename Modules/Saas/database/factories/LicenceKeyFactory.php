<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\LicenceKey;
use Modules\Saas\Models\Subscription;

/**
 * @extends Factory<LicenceKey>
 */
class LicenceKeyFactory extends Factory
{
    protected $model = LicenceKey::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'subscription_id' => Subscription::factory(),
            'key_value' => 'SERP-'.strtoupper(fake()->unique()->bothify('????-????-????')),
            'installation_uuid' => null,
            'last_validated_at' => null,
            'offline_grace_days' => 14,
            'status' => 'active',
        ];
    }
}
