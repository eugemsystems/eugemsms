<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\MovementLogEntryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Student;

/**
 * Book F BRD-02 §2/BR-BRD-02-017 — APPEND-ONLY. Maps to the
 * `movement_log` table — named `Entry` here since `MovementLog` reads
 * better as a concept than a row.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $checkpoint_id
 * @property string $direction
 * @property Carbon $occurred_at
 * @property string $method
 * @property int|null $recorded_by
 * @property int|null $exeat_id
 * @property bool $is_authorised
 * @property string|null $note
 */
class MovementLogEntry extends Model
{
    protected $table = 'movement_log';

    use BelongsToSchool;

    /** @use HasFactory<MovementLogEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'student_id', 'checkpoint_id', 'direction', 'occurred_at', 'method', 'recorded_by', 'exeat_id', 'is_authorised', 'note'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_authorised' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MovementLogEntryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('movement_log is append-only — an entry is never amended.', []);
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('movement_log is append-only — an entry is never deleted.', []);
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
     * @return BelongsTo<MovementCheckpoint, $this>
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(MovementCheckpoint::class, 'checkpoint_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
