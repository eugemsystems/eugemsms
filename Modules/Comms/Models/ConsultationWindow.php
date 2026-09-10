<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ConsultationWindowFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;

/**
 * Book I COM-07 §2 ⭐/BR-COM-07-005.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int $staff_id
 * @property string $event_name
 * @property int $slot_duration_minutes
 * @property Carbon $available_from
 * @property Carbon $available_to
 * @property Carbon|null $booking_opens_at
 * @property Carbon|null $booking_closes_at
 */
class ConsultationWindow extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ConsultationWindowFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'term_id', 'staff_id', 'event_name', 'slot_duration_minutes',
        'available_from', 'available_to', 'booking_opens_at', 'booking_closes_at',
    ];

    protected function casts(): array
    {
        return [
            'available_from' => 'datetime',
            'available_to' => 'datetime',
            'booking_opens_at' => 'datetime',
            'booking_closes_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ConsultationWindowFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<ConsultationBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ConsultationBooking::class, 'window_id');
    }
}
