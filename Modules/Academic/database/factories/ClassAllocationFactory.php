<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<ClassAllocation>
 */
class ClassAllocationFactory extends Factory
{
    protected $model = ClassAllocation::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'class_id' => SchoolClass::factory()->for($school),
            'allocation_type' => 'initial',
            'effective_from' => now()->toDateString(),
            'status' => 'confirmed',
            'allocated_by' => User::factory(),
        ];
    }
}
