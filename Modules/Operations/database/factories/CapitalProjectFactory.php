<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Operations\Models\CapitalProject;

/**
 * @extends Factory<CapitalProject>
 */
class CapitalProjectFactory extends Factory
{
    protected $model = CapitalProject::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'project_number' => 'CP-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'New Science Block',
            'budget_minor' => 50000000,
            'currency' => 'USD',
            'starts_on' => now()->toDateString(),
            'status' => 'planning',
            'capitalise_on_completion' => true,
        ];
    }
}
