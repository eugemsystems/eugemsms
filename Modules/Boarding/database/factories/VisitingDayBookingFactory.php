<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\VisitingDay;
use Modules\Boarding\Models\VisitingDayBooking;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<VisitingDayBooking>
 */
class VisitingDayBookingFactory extends Factory
{
    protected $model = VisitingDayBooking::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'visiting_day_id' => VisitingDay::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'slot_starts_at' => '10:00:00',
            'party_size' => 2,
            'status' => 'booked',
            'booked_at' => now(),
        ];
    }
}
