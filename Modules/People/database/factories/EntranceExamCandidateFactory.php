<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\People\Models\Application;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;

/**
 * @extends Factory<EntranceExamCandidate>
 */
class EntranceExamCandidateFactory extends Factory
{
    protected $model = EntranceExamCandidate::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'exam_id' => EntranceExam::factory()->for($school), 'application_id' => Application::factory()->for($school), 'candidate_number' => 'C'.fake()->unique()->numerify('####'),
        ];
    }
}
