<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentPriorSchool;

/**
 * @extends Factory<StudentPriorSchool>
 */
class StudentPriorSchoolFactory extends Factory
{
    protected $model = StudentPriorSchool::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school), 'school_name' => 'Chitungwiza Primary', 'country' => 'ZW',
        ];
    }
}
