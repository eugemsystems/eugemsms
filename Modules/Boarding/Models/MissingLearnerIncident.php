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
use Modules\Boarding\Database\Factories\MissingLearnerIncidentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book F BRD-02 §2/§3 ⭐ — APPEND-ONLY once opened (BR-BRD-02-013/
 * AC-BRD-02-006). Only the resolution/ladder columns listed in
 * `MUTABLE_AFTER_CREATE` ever change after creation; no permission
 * level, including Super Admin, has a delete path — `booted()` blocks
 * both unconditionally, mirroring `LearnerSubjectEnrolment`'s own
 * guard.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $student_id
 * @property int|null $roll_call_id
 * @property int|null $escalation_profile_id
 * @property Carbon $first_missed_at
 * @property Carbon|null $last_seen_at
 * @property string|null $last_seen_location
 * @property string|null $last_seen_source
 * @property int $current_step
 * @property string $status
 * @property Carbon|null $located_at
 * @property int|null $located_by
 * @property string|null $location_found
 * @property string|null $outcome
 * @property string|null $outcome_note
 * @property Carbon|null $guardians_notified_at
 * @property Carbon|null $authorities_notified_at
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 */
class MissingLearnerIncident extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MissingLearnerIncidentFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'last_seen_at', 'last_seen_location', 'last_seen_source', 'current_step', 'status',
        'located_at', 'located_by', 'location_found', 'outcome', 'outcome_note',
        'guardians_notified_at', 'authorities_notified_at', 'closed_by', 'closed_at',
    ];

    protected $fillable = [
        'school_id', 'term_id', 'student_id', 'roll_call_id', 'escalation_profile_id', 'first_missed_at', 'last_seen_at',
        'last_seen_location', 'last_seen_source', 'current_step', 'status', 'located_at',
        'located_by', 'location_found', 'outcome', 'outcome_note', 'guardians_notified_at',
        'authorities_notified_at', 'closed_by', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'first_missed_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'located_at' => 'datetime',
            'guardians_notified_at' => 'datetime',
            'authorities_notified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MissingLearnerIncidentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A missing_learner_incidents row may only change its resolution/ladder columns after creation (BR-BRD-02-013).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException(
                'A missing_learner_incidents row is never deleted, at any permission level (BR-BRD-02-013).',
                [],
            );
        });
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<RollCall, $this>
     */
    public function rollCall(): BelongsTo
    {
        return $this->belongsTo(RollCall::class, 'roll_call_id');
    }

    /**
     * @return BelongsTo<EscalationProfile, $this>
     */
    public function escalationProfile(): BelongsTo
    {
        return $this->belongsTo(EscalationProfile::class, 'escalation_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function locatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'located_by');
    }

    /**
     * @return HasMany<EscalationAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(EscalationAction::class, 'incident_id')->orderBy('occurred_at');
    }
}
