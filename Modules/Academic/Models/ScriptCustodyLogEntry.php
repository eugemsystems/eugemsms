<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ScriptCustodyLogEntryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Staff;

/**
 * Book E ACA-07 §2/BR-ACA-07-012 — APPEND-ONLY, mirroring
 * `LearnerSubjectEnrolment`'s own booted() guard rather than a
 * model-level `update()`/`delete()` override, since this table has no
 * legitimate mutable field at all (unlike `LegacyCalaRecord`, which
 * is read-only at the row level for a different reason).
 *
 * @property int $id
 * @property int $school_id
 * @property int $batch_id
 * @property int|null $from_staff_id
 * @property int|null $to_staff_id
 * @property string $action
 * @property int $script_count
 * @property string|null $discrepancy_note
 * @property Carbon $occurred_at
 * @property int $recorded_by
 */
class ScriptCustodyLogEntry extends Model
{
    protected $table = 'script_custody_log';

    use BelongsToSchool;

    /** @use HasFactory<ScriptCustodyLogEntryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'batch_id', 'from_staff_id', 'to_staff_id', 'action', 'script_count', 'discrepancy_note', 'occurred_at', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ScriptCustodyLogEntryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('script_custody_log is append-only — a custody entry is never amended.', []);
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('script_custody_log is append-only — a custody entry is never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<ScriptBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScriptBatch::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function fromStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'from_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function toStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'to_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
