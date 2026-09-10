<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<ExaminationMark>
 */
class ExaminationMarkFactory extends Factory
{
    protected $model = ExaminationMark::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'paper_id' => ExaminationPaper::factory()->for($school),
            'candidate_id' => ExaminationCandidate::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'is_absent' => false,
            'status' => 'pending',
            'version' => 1,
        ];
    }
}
