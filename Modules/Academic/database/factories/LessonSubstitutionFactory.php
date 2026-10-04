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
        // $term is referenced twice below (directly, and inside the
        // nested TimetableSlot factory's own state override) — the
        // same `TimetableSlotFactory`-documented gotcha: a lazy Factory
        // instance referenced twice in one definition() is resolved
        // independently each time, not deduped, which previously
        // created two different `terms` rows for the same
        // (school_id, academic_year_id, number) and tripped its own
        // unique constraint. Created eagerly here for the same reason.
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

        return [
            'school_id' => $school->id,
            'term_id' => $term->id,
            'timetable_slot_id' => TimetableSlot::factory()->for($school)->state(['term_id' => $term->id]),
            'substitution_date' => now()->toDateString(),
            'absent_staff_id' => Staff::factory()->for($school),
            'reason' => 'sick',
            'status' => 'pending',
        ];
    }
}
