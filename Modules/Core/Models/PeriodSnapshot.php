<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\PeriodSnapshotFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book A CORE-03 §2 — the forensic anchor. BR-CORE-03-015/016: never
 * deleted, never edited. `$timestamps = false` — `taken_at` is the row's
 * only point-in-time column, set explicitly by `ACT-TakePeriodSnapshot`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $snapshot_type
 * @property Carbon $taken_at
 * @property int|null $taken_by
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string|null $previous_hash
 * @property array<string, mixed> $row_counts
 * @property-read AcademicYear $academicYear
 * @property-read Term $term
 * @property-read User|null $takenBy
 */
class PeriodSnapshot extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodSnapshotFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id',
        'academic_year_id',
        'term_id',
        'snapshot_type',
        'taken_at',
        'taken_by',
        'payload',
        'payload_hash',
        'previous_hash',
        'row_counts',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'payload' => 'array',
            'row_counts' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodSnapshotFactory::new();
    }

    /**
     * BR-CORE-03-016, application-level enforcement of the same guarantee
     * the DB grants provide in production (Volume 1 "append-only at the
     * database grant level" — see the migration's own note on that).
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('Period snapshots are append-only and can never be edited.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('Period snapshots are append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}
