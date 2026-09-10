<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<RollCall>
 */
class RollCallFactory extends Factory
{
    protected $model = RollCall::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'roll_call_point_id' => RollCallPoint::factory()->for($school),
            'hostel_id' => Hostel::factory()->for($school),
            'roll_date' => now()->toDateString(),
            'scheduled_at' => now(),
            'status' => 'pending',
        ];
    }
}
