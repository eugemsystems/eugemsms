<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Academic\Models\CbtTest;
use Modules\People\Models\Student;

/**
 * @extends Factory<CbtCandidateAttempt>
 */
class CbtCandidateAttemptFactory extends Factory
{
    protected $model = CbtCandidateAttempt::class;

    public function definition(): array
    {
        $test = CbtTest::factory()->create();

        return [
            'school_id' => $test->school_id,
            'test_id' => $test->id,
            'student_id' => Student::factory()->create(['school_id' => $test->school_id])->id,
            'seeded_question_order' => $test->question_ids ?? [],
            'started_at' => Carbon::now(),
            'extra_time_minutes' => 0,
            'tab_switch_count' => 0,
            'status' => 'in_progress',
        ];
    }
}
