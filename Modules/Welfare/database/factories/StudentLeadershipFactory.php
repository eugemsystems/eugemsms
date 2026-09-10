<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Models\StudentLeadership;

/**
 * @extends Factory<StudentLeadership>
 */
class StudentLeadershipFactory extends Factory
{
    protected $model = StudentLeadership::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'academic_year_id' => AcademicYear::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'role_title' => 'House Prefect',
            'starts_on' => now()->toDateString(),
            'appointed_by' => User::factory(),
            'status' => 'active',
        ];
    }
}
