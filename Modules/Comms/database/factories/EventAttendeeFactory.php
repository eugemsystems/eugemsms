<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\EventAttendee;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Models\School;

/**
 * @extends Factory<EventAttendee>
 */
class EventAttendeeFactory extends Factory
{
    protected $model = EventAttendee::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'registration_id' => EventRegistration::factory()->for($school),
            'attendee_type' => 'guardian',
            'attendee_name' => $this->faker->name(),
            'guardian_id' => null,
            'party_size' => 1,
            'ad_hoc_charge_id' => null,
            'ticket_receipt_id' => null,
            'waitlist_position' => null,
            'checked_in_at' => null,
            'status' => 'registered',
        ];
    }
}
