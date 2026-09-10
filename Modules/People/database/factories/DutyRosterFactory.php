<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\DutyRoster;

/**
 * @extends Factory<DutyRoster>
 */
class DutyRosterFactory extends Factory
{
    protected $model = DutyRoster::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'duty_type' => 'teacher_on_duty',
            'name' => 'Teacher on Duty',
            'rotation_pattern' => 'weekly',
            'is_active' => true,
        ];
    }
}
