<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Subject;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;
use Modules\People\Models\TeacherAllocation;

/**
 * @extends Factory<TeacherAllocation>
 */
class TeacherAllocationFactory extends Factory
{
    protected $model = TeacherAllocation::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'staff_id' => Staff::factory()->for($school),
            'subject_id' => Subject::factory()->for($school),
            'class_id' => SchoolClass::factory()->for($school)->for($year),
            'role' => 'teacher',
            'weekly_periods' => 4,
            'is_class_teacher' => false,
            'starts_on' => now()->toDateString(),
            'status' => 'active',
            'allocated_by' => User::factory(),
        ];
    }
}
