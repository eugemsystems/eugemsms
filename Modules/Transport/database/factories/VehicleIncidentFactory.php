<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleIncident;

/**
 * @extends Factory<VehicleIncident>
 */
class VehicleIncidentFactory extends Factory
{
    protected $model = VehicleIncident::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'vehicle_id' => fn (array $attributes): int => Vehicle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'incident_type' => 'breakdown',
            'occurred_at' => now(),
            'location' => 'Along Enterprise Road',
            'description' => 'Vehicle broke down en route.',
            'injuries' => false,
            'reported_by' => User::factory(),
            'status' => 'reported',
        ];
    }
}
