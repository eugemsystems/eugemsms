<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\VisitingDay;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<VisitingDay>
 */
class VisitingDayFactory extends Factory
{
    protected $model = VisitingDay::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'visit_date' => now()->addWeek()->toDateString(),
            'name' => 'Term Visiting Day',
            'starts_at' => '09:00:00',
            'ends_at' => '16:00:00',
            'status' => 'planned',
        ];
    }
}
