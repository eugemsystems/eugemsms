<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\HardwareDevice;

/**
 * @extends Factory<HardwareDevice>
 */
class HardwareDeviceFactory extends Factory
{
    protected $model = HardwareDevice::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'device_type' => 'rfid_reader',
            'location' => 'Main Gate',
            'purpose' => 'gate',
            'api_client_id' => ApiClient::factory()->for($school)->create(['client_type' => 'hardware_device']),
            'firmware_version' => '1.0.0',
            'status' => 'offline',
        ];
    }
}
