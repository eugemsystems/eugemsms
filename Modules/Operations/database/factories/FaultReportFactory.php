<?php

declare(strict_types=1);

namespace Modules\Operations\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Operations\Models\FaultReport;

/**
 * @extends Factory<FaultReport>
 */
class FaultReportFactory extends Factory
{
    protected $model = FaultReport::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'report_number' => 'FR-'.fake()->unique()->numberBetween(10000, 99999),
            'location' => 'Boys Hostel A, Room 12',
            'category' => 'plumbing',
            'description' => 'Leaking tap in the ablutions.',
            'severity' => 'routine',
            'affects_safety' => false,
            'affects_teaching' => false,
            'reported_by' => User::factory(),
            'reported_at' => now(),
            'status' => 'reported',
        ];
    }

    public function safetyCritical(): self
    {
        return $this->state(fn (): array => ['affects_safety' => true, 'severity' => 'emergency']);
    }
}
