<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\MeetingAttendance;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Models\School;

/**
 * @extends Factory<MeetingAttendance>
 */
class MeetingAttendanceFactory extends Factory
{
    protected $model = MeetingAttendance::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'meeting_id' => ScheduledMeeting::factory()->for($school),
            'participant_identifier' => $this->faker->safeEmail(),
            'student_id' => null,
            'joined_at' => now(),
            'left_at' => null,
            'duration_seconds' => null,
            'match_confidence' => null,
        ];
    }
}
