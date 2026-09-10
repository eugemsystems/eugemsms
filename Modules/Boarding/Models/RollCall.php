<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\RollCallFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;

/**
 * Book F BRD-02 §2/BR-BRD-02-002/007 ⭐ — one occurrence. `expected_count`
 * is derived from active bed allocations at open time, never a stored
 * list re-read later; `roll_date` is set by the server.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $roll_call_point_id
 * @property int $hostel_id
 * @property Carbon $roll_date
 * @property Carbon $scheduled_at
 * @property int $expected_count
 * @property int $present_count
 * @property int $accounted_count
 * @property int $missing_count
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $conducted_by
 * @property string|null $device_source
 */
class RollCall extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RollCallFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'roll_call_point_id', 'hostel_id', 'roll_date', 'scheduled_at',
        'expected_count', 'present_count', 'accounted_count', 'missing_count', 'status',
        'started_at', 'completed_at', 'conducted_by', 'device_source',
    ];

    protected function casts(): array
    {
        return [
            'roll_date' => 'date',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RollCallFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<RollCallPoint, $this>
     */
    public function point(): BelongsTo
    {
        return $this->belongsTo(RollCallPoint::class, 'roll_call_point_id');
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    /**
     * @return HasMany<RollCallRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(RollCallRecord::class, 'roll_call_id');
    }
}
