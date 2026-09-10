<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\ExitInterview;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<ExitInterview>
 */
class ExitInterviewFactory extends Factory
{
    protected $model = ExitInterview::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'student_id' => Student::factory()->for($school),
            'guardian_id' => null,
            'requested_at' => now(),
            'completed_at' => null,
            'primary_reason' => null,
            'detail' => null,
            'would_recommend' => null,
            'response_source' => null,
        ];
    }
}
