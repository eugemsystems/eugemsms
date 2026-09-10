<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * @extends Factory<ConsultationWindow>
 */
class ConsultationWindowFactory extends Factory
{
    protected $model = ConsultationWindow::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school),
            'staff_id' => Staff::factory()->for($school),
            'event_name' => 'Term Parents Evening',
            'slot_duration_minutes' => 10,
            'available_from' => now()->addDays(7)->setTime(16, 0),
            'available_to' => now()->addDays(7)->setTime(18, 0),
            'booking_opens_at' => null,
            'booking_closes_at' => null,
        ];
    }
}
