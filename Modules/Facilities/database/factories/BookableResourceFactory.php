<?php

declare(strict_types=1);

namespace Modules\Facilities\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Facilities\Models\BookableResource;
use Modules\Finance\Models\CostCentre;

/**
 * @extends Factory<BookableResource>
 */
class BookableResourceFactory extends Factory
{
    protected $model = BookableResource::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'HALL-'.fake()->unique()->numberBetween(1, 999),
            'name' => 'Main Hall',
            'resource_type' => 'hall',
            'capacity' => 300,
            'is_externally_hireable' => true,
            'hire_rate_minor' => 10000,
            'hire_rate_unit' => 'day',
            'hire_currency' => 'USD',
            'deposit_minor' => 30000,
            'requires_setup_minutes' => 90,
            'requires_cleaning_minutes' => 60,
            'booking_lead_time_hours' => 24,
            'cost_centre_id' => fn (array $attributes): int => CostCentre::factory()->create(['school_id' => $attributes['school_id']])->id,
            'is_active' => true,
        ];
    }
}
