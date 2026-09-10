<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\PortalDevice;

/**
 * @extends Factory<PortalDevice>
 */
class PortalDeviceFactory extends Factory
{
    protected $model = PortalDevice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => (string) $this->faker->unique()->uuid(),
            'platform' => 'android',
            'push_token' => (string) $this->faker->sha256(),
            'push_token_updated_at' => now(),
            'app_version' => '1.0.0',
            'os_version' => '14',
            'last_active_at' => now(),
            'requires_biometric_lock' => false,
            'app_pin_hash' => null,
            'is_active' => true,
            'revoked_at' => null,
        ];
    }
}
