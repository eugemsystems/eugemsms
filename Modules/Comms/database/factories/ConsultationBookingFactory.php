<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Core\Models\School;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * @extends Factory<ConsultationBooking>
 */
class ConsultationBookingFactory extends Factory
{
    protected $model = ConsultationBooking::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'window_id' => ConsultationWindow::factory()->for($school),
            'guardian_id' => Guardian::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'slot_starts_at' => now()->addDays(7)->setTime(16, 0),
            'meeting_id' => null,
            'status' => 'booked',
        ];
    }
}
