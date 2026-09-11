<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\ObservationRubric;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<LessonObservation>
 */
class LessonObservationFactory extends Factory
{
    protected $model = LessonObservation::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'observed_staff_id' => Staff::factory()->for($school),
            'observer_staff_id' => Staff::factory()->for($school),
            'rubric_id' => ObservationRubric::factory()->for($school),
            'observed_at' => now(),
            'scores' => ['Lesson planning' => 'proficient', 'Learner engagement' => 'developing'],
            'teacher_acknowledged' => false,
        ];
    }
}
