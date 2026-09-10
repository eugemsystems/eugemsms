<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Intelligence\Models\EnrolmentForecast;

/**
 * @extends Factory<EnrolmentForecast>
 */
class EnrolmentForecastFactory extends Factory
{
    protected $model = EnrolmentForecast::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'grade_level_id' => GradeLevel::factory()->for($school),
            'projected_intake' => 30,
            'projected_attrition' => 5,
            'confidence_band' => 'low',
            'basis_note' => 'Test forecast basis.',
            'computed_at' => now(),
        ];
    }
}
