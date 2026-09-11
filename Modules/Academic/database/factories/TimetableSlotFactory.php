<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<TimetableSlot>
 */
class TimetableSlotFactory extends Factory
{
    protected $model = TimetableSlot::class;

    public function definition(): array
    {
        // $term is created eagerly, then referenced by id in two places
        // (the timetable and this slot itself) — a lazy Factory
        // instance referenced twice in one definition() is resolved
        // independently each time, not deduped, which previously
        // created two different `terms` rows for the same
        // (school_id, academic_year_id, number) and tripped its own
        // unique constraint.
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();
        $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
        $structure = PeriodStructure::factory()->for($school)->create(['academic_year_id' => $year->id]);
        $periodSlot = PeriodSlot::factory()->for($school)->create(['structure_id' => $structure->id]);

        return [
            'school_id' => $school->id,
            'timetable_id' => Timetable::factory()->create([
                'school_id' => $school->id,
                'academic_year_id' => $year->id,
                'term_id' => $term->id,
                'structure_id' => $structure->id,
            ])->id,
            'term_id' => $term->id,
            'period_slot_id' => $periodSlot->id,
            'cycle_day' => 1,
            'period_number' => 1,
            'subject_id' => Subject::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
        ];
    }
}
