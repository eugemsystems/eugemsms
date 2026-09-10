<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LessonSubstitution;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<LessonSubstitution>
 */
class LessonSubstitutionFactory extends Factory
{
    protected $model = LessonSubstitution::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $term = Term::factory()->for($school)->for($year, 'academicYear');

        return [
            'school_id' => $school,
            'term_id' => $term,
            'timetable_slot_id' => TimetableSlot::factory()->for($school)->state(['term_id' => $term]),
            'substitution_date' => now()->toDateString(),
            'absent_staff_id' => Staff::factory()->for($school),
            'reason' => 'sick',
            'status' => 'pending',
        ];
    }
}
