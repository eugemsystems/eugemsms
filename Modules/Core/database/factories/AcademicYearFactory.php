<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        // A wide range so two years for the same school (a common test
        // setup, and increasingly common as more modules register many
        // tenant-model factories against one shared school in the
        // tenancy isolation test) rarely collide on the (school_id,
        // name) unique index — 100 values was too narrow once dozens
        // of factories draw from it in a single test run.
        $year = (string) fake()->numberBetween(1000, 9999);

        return [
            'school_id' => School::factory(),
            'name' => $year,
            'starts_on' => "{$year}-01-01",
            'ends_on' => "{$year}-12-31",
            'is_current' => false,
            'academic_state' => 'open',
            'financial_state' => 'open',
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes): array => ['is_current' => true]);
    }
}
