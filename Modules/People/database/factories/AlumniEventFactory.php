<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Models\School;
use Modules\People\Models\AlumniEvent;

/**
 * @extends Factory<AlumniEvent>
 */
class AlumniEventFactory extends Factory
{
    protected $model = AlumniEvent::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'calendar_event_id' => CalendarEvent::factory()->for($school),
            'event_type' => 'reunion',
            'requires_ticket' => false,
        ];
    }
}
