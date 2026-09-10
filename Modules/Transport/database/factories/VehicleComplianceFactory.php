<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleCompliance;

/**
 * @extends Factory<VehicleCompliance>
 */
class VehicleComplianceFactory extends Factory
{
    protected $model = VehicleCompliance::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'vehicle_id' => fn (array $attributes): int => Vehicle::factory()->create(['school_id' => $attributes['school_id']])->id,
            'compliance_type' => 'certificate_of_fitness',
            'expires_on' => now()->addMonths(6)->toDateString(),
            'status' => 'valid',
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_on' => now()->subDay()->toDateString(), 'status' => 'expired']);
    }
}
