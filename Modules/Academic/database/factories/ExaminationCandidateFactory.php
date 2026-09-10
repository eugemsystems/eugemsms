<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<ExaminationCandidate>
 */
class ExaminationCandidateFactory extends Factory
{
    protected $model = ExaminationCandidate::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'session_id' => ExaminationSession::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'index_number' => 'C/1/'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'entry_status' => 'provisional',
            'entered_subjects' => [],
            'entry_invoiced' => false,
        ];
    }
}
