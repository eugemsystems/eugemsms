<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Timetable;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<Timetable>
 */
class TimetableFactory extends Factory
{
    protected $model = Timetable::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'structure_id' => PeriodStructure::factory()->for($school)->state(['academic_year_id' => $year]),
            'name' => 'Term Timetable',
            'version' => 1,
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }
}
