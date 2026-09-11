<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\People\Models\Student;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    protected $model = AssignmentSubmission::class;

    public function definition(): array
    {
        $assignment = Assignment::factory()->create();

        return [
            'school_id' => $assignment->school_id,
            'assignment_id' => $assignment->id,
            'student_id' => Student::factory()->create(['school_id' => $assignment->school_id])->id,
            'attempt_number' => 1,
            'submitted_text' => $this->faker->paragraph(),
            'submitted_at' => now(),
            'is_late' => false,
            'similarity_flag' => false,
            'status' => 'submitted',
        ];
    }
}
