<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\TimetableConstraint;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<TimetableConstraint>
 */
class TimetableConstraintFactory extends Factory
{
    protected $model = TimetableConstraint::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'constraint_type' => 'teacher_max_per_day',
            'severity' => 'soft',
            'weight' => 1,
            'is_active' => true,
        ];
    }
}
