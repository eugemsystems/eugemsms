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
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);
        $term = Term::factory()->for($school)->for($year, 'academicYear');
        $structure = PeriodStructure::factory()->for($school)->state(['academic_year_id' => $year]);
        $periodSlot = PeriodSlot::factory()->for($school)->state(['structure_id' => $structure]);

        return [
            'school_id' => $school,
            'timetable_id' => Timetable::factory()->state([
                'school_id' => $school,
                'academic_year_id' => $year,
                'term_id' => $term,
                'structure_id' => $structure,
            ]),
            'term_id' => $term,
            'period_slot_id' => $periodSlot,
            'cycle_day' => 1,
            'period_number' => 1,
            'subject_id' => Subject::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
        ];
    }
}
