<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentSibling;

/**
 * @extends Factory<StudentSibling>
 */
class StudentSiblingFactory extends Factory
{
    protected $model = StudentSibling::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school), 'sibling_student_id' => Student::factory()->for($school), 'relationship' => 'full',
        ];
    }
}
