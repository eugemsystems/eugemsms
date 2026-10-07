<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\People\Models\StudentMerge;

/**
 * @extends Factory<StudentMerge>
 */
class StudentMergeFactory extends Factory
{
    protected $model = StudentMerge::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'surviving_student_id' => Student::factory()->for($school),
            'merged_student_id' => Student::factory()->for($school),
            'merged_student_admission_number' => 'ADM/'.$this->faker->unique()->numerify('######'),
            'merged_by' => User::factory(),
            'merged_at' => now(),
        ];
    }
}
