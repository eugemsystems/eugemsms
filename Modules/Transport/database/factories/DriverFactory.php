<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Transport\Models\Driver;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'staff_id' => fn (array $attributes): int => Staff::factory()->create(['school_id' => $attributes['school_id']])->id,
            'licence_number' => strtoupper(fake()->bothify('??######')),
            'licence_classes' => ['2', '4'],
            'licence_expires_on' => now()->addYear()->toDateString(),
            'medical_expires_on' => now()->addYear()->toDateString(),
            'defensive_expires_on' => now()->addYear()->toDateString(),
            'years_experience' => 8,
            'status' => 'active',
            'incident_count' => 0,
        ];
    }

    public function withExpiredLicence(): self
    {
        return $this->state(fn (): array => ['licence_expires_on' => now()->subDay()->toDateString(), 'status' => 'expired_documents']);
    }
}
