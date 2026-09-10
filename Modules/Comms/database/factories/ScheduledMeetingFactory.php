<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MeetingProvider;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<ScheduledMeeting>
 */
class ScheduledMeetingFactory extends Factory
{
    protected $model = ScheduledMeeting::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school),
            'meeting_type' => 'staff_meeting',
            'provider_id' => MeetingProvider::factory()->for($school),
            'timetable_slot_id' => null,
            'provider_meeting_id' => 'fake-'.$this->faker->unique()->numerify('########'),
            'join_url' => 'https://meet.example.com/j/'.$this->faker->numerify('##########'),
            'host_url' => 'https://meet.example.com/s/'.$this->faker->numerify('##########'),
            'passcode' => $this->faker->numerify('######'),
            'starts_at' => now()->addDay(),
            'duration_minutes' => 30,
            'host_staff_id' => null,
            'waiting_room_enabled' => true,
            'recording_enabled' => false,
            'status' => 'scheduled',
        ];
    }
}
