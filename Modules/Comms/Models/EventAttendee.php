<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\EventAttendeeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\AdHocCharge;
use Modules\People\Models\Guardian;

/**
 * Book I COM-06 §2 ⭐/BR-COM-06-006/007 (AC-COM-06-003/004). `status`
 * carries one value beyond the spec's literal four — `waitlisted` —
 * see the owning migration's docblock.
 *
 * @property int $id
 * @property int $school_id
 * @property int $registration_id
 * @property string $attendee_type
 * @property string $attendee_name
 * @property int|null $guardian_id
 * @property int $party_size
 * @property int|null $ad_hoc_charge_id
 * @property int|null $ticket_receipt_id
 * @property int|null $waitlist_position
 * @property Carbon|null $checked_in_at
 * @property string $status
 */
class EventAttendee extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EventAttendeeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'registration_id', 'attendee_type', 'attendee_name', 'guardian_id',
        'party_size', 'ad_hoc_charge_id', 'ticket_receipt_id', 'waitlist_position',
        'checked_in_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EventAttendeeFactory::new();
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return BelongsTo<AdHocCharge, $this>
     */
    public function adHocCharge(): BelongsTo
    {
        return $this->belongsTo(AdHocCharge::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid' || $this->ticket_receipt_id !== null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['registered', 'paid', 'checked_in'], true);
    }
}
