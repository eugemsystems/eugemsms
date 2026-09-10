<?php

declare(strict_types=1);

namespace Modules\Comms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Models\School;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    protected $model = EventRegistration::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'calendar_event_id' => CalendarEvent::factory()->for($school),
            'capacity' => null,
            'requires_ticket' => false,
            'ticket_price_minor' => null,
            'ticket_currency' => null,
            'fee_component_id' => null,
            'rsvp_deadline' => null,
            'registered_count' => 0,
        ];
    }

    public function ticketed(int $priceMinor = 500, string $currency = 'USD'): self
    {
        return $this->state(fn (): array => [
            'requires_ticket' => true,
            'ticket_price_minor' => $priceMinor,
            'ticket_currency' => $currency,
        ]);
    }
}
