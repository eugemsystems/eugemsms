<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;

/**
 * @extends Factory<StudentEnrolment>
 */
class StudentEnrolmentFactory extends Factory
{
    protected $model = StudentEnrolment::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => Term::factory(),
            'section_id' => SchoolSection::factory(),
            'grade_level_id' => GradeLevel::factory(),
            'enrolment_type' => 'FULL_TIME',
            'residency' => 'DAY',
            'status' => 'active',
            'started_on' => now()->toDateString(),
            'is_repeat' => false,
        ];
    }
}
