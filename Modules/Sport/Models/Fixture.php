<?php

declare(strict_types=1);

namespace Modules\Sport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Facilities\Models\ResourceBooking;
use Modules\Sport\Database\Factories\FixtureFactory;
use Modules\Transport\Models\Trip;
use Modules\Welfare\Models\HealthIncident;

/**
 * Book H2 OPS-07 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $team_id
 * @property string $opponent
 * @property string $fixture_type
 * @property string $venue_type
 * @property int|null $venue_id
 * @property string|null $venue_name
 * @property Carbon $fixture_date
 * @property string|null $start_time
 * @property string|null $departure_time
 * @property string|null $return_time
 * @property int|null $trip_id
 * @property int|null $booking_id
 * @property array<int, int>|null $squad_student_ids
 * @property array<int, int>|null $staff_ids
 * @property string|null $result
 * @property string|null $score_for
 * @property string|null $score_against
 * @property string|null $match_report
 * @property string $status
 * @property Carbon|null $guardians_notified_at
 */
class Fixture extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'team_id', 'opponent', 'fixture_type', 'venue_type',
        'venue_id', 'venue_name', 'fixture_date', 'start_time', 'departure_time',
        'return_time', 'trip_id', 'booking_id', 'squad_student_ids', 'staff_ids',
        'result', 'score_for', 'score_against', 'match_report', 'status', 'guardians_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'fixture_date' => 'date',
            'squad_student_ids' => 'array',
            'staff_ids' => 'array',
            'guardians_notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FixtureFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<ResourceBooking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(ResourceBooking::class);
    }

    /**
     * @return HasMany<HealthIncident, $this>
     */
    public function healthIncidents(): HasMany
    {
        return $this->hasMany(HealthIncident::class);
    }
}
