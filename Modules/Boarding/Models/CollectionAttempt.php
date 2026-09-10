<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\CollectionAttemptFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book F BRD-03 §2/§3 ⭐/BR-BRD-03-012 — APPEND-ONLY, including every
 * refusal. No UPDATE, no DELETE, at any permission level.
 * `attempted_by_id_no` is encrypted at rest.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int|null $exeat_id
 * @property int|null $attempted_by_guardian_id
 * @property string $attempted_by_name
 * @property string|null $attempted_by_id_no
 * @property string|null $claimed_relationship
 * @property string $outcome
 * @property string|null $refusal_reason
 * @property bool $verified_by_photo
 * @property int $gate_staff_id
 * @property int|null $escalated_to_staff_id
 * @property Carbon $occurred_at
 * @property string|null $notes
 */
class CollectionAttempt extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CollectionAttemptFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'exeat_id', 'attempted_by_guardian_id', 'attempted_by_name',
        'attempted_by_id_no', 'claimed_relationship', 'outcome', 'refusal_reason', 'verified_by_photo',
        'gate_staff_id', 'escalated_to_staff_id', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'attempted_by_id_no' => 'encrypted',
            'verified_by_photo' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CollectionAttemptFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('collection_attempts is append-only — a record is never amended.', []);
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('collection_attempts is append-only — a record is never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Exeat, $this>
     */
    public function exeat(): BelongsTo
    {
        return $this->belongsTo(Exeat::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function attemptedByGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'attempted_by_guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gateStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_staff_id');
    }
}
