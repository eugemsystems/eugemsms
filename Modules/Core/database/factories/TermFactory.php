<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'number' => 1,
            'name' => 'Term 1',
            'starts_on' => now()->startOfYear(),
            'ends_on' => now()->startOfYear()->addMonths(3),
            'is_current' => false,
            'academic_state' => 'open',
            'financial_state' => 'open',
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes): array => ['is_current' => true]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'academic_state' => 'locked',
            'financial_state' => 'locked',
        ]);
    }

    public function softClosed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'academic_state' => 'soft_closed',
            'financial_state' => 'soft_closed',
        ]);
    }
}
