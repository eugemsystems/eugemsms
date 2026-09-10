<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\EventRegistrationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-06 §2 ⭐/BR-COM-06-006/007.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $calendar_event_id
 * @property int|null $capacity
 * @property bool $requires_ticket
 * @property int|null $ticket_price_minor
 * @property string|null $ticket_currency
 * @property int|null $fee_component_id
 * @property Carbon|null $rsvp_deadline
 * @property int $registered_count
 */
class EventRegistration extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EventRegistrationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'calendar_event_id', 'capacity', 'requires_ticket', 'ticket_price_minor',
        'ticket_currency', 'fee_component_id', 'rsvp_deadline', 'registered_count',
    ];

    protected function casts(): array
    {
        return [
            'requires_ticket' => 'boolean',
            'rsvp_deadline' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EventRegistrationFactory::new();
    }

    /**
     * @return BelongsTo<CalendarEvent, $this>
     */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class);
    }

    /**
     * @return HasMany<EventAttendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->registered_count >= $this->capacity;
    }
}
