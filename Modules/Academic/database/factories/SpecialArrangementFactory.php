<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<SpecialArrangement>
 */
class SpecialArrangementFactory extends Factory
{
    protected $model = SpecialArrangement::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'session_id' => ExaminationSession::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'arrangement_type' => 'extra_time',
            'extra_time_percent' => 25,
            'justification' => 'Educational psychologist report recommends 25% extra time.',
            'status' => 'requested',
        ];
    }
}
