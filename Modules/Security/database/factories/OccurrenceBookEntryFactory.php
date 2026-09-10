<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\OccurrenceBookEntry;

/**
 * @extends Factory<OccurrenceBookEntry>
 */
class OccurrenceBookEntryFactory extends Factory
{
    protected $model = OccurrenceBookEntry::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'entry_number' => fake()->unique()->numberBetween(1, 1000000),
            'occurred_at' => now(),
            'recorded_at' => now(),
            'category' => 'observation',
            'description' => 'Routine perimeter check, no issues.',
            'recorded_by' => User::factory(),
        ];
    }
}
