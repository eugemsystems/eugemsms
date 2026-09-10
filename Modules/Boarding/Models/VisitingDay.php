<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\VisitingDayFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;

/**
 * Book F BRD-03 §2/BR-BRD-03-022.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property Carbon $visit_date
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property int|null $slot_duration_minutes
 * @property int|null $max_per_slot
 * @property array<int, int>|null $applies_to_hostels
 * @property Carbon|null $booking_opens_at
 * @property Carbon|null $booking_closes_at
 * @property string $status
 */
class VisitingDay extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VisitingDayFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'visit_date', 'name', 'starts_at', 'ends_at', 'slot_duration_minutes',
        'max_per_slot', 'applies_to_hostels', 'booking_opens_at', 'booking_closes_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'applies_to_hostels' => 'array',
            'booking_opens_at' => 'datetime',
            'booking_closes_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VisitingDayFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return HasMany<VisitingDayBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(VisitingDayBooking::class, 'visiting_day_id');
    }
}
