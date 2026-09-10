<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AttendanceRecordFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-04 §2/BR-ACA-04-008/018. `status` on its own may never
 * change after creation — that would silently erase what a mark
 * originally said. A correction is only ever made through
 * `AmendAttendanceRecordAction`, which writes `status` together with
 * `original_status`/`amended_by`/`amended_at`/`amendment_reason` in
 * the same update, all four of which are in `MUTABLE_AFTER_CREATE`
 * precisely so that whitelist can't be satisfied by changing `status`
 * alone. See the owning migration's docblock for `idempotency_key`/
 * `original_status` — additions beyond the spec's literal schema.
 *
 * @property int $id
 * @property int $school_id
 * @property int $session_id
 * @property int $student_id
 * @property int $term_id
 * @property Carbon $session_date
 * @property string $status
 * @property string|null $original_status
 * @property int|null $reason_code_id
 * @property int|null $minutes_late
 * @property string|null $note
 * @property string|null $idempotency_key
 * @property int|null $marked_by
 * @property Carbon $marked_at
 * @property int|null $amended_by
 * @property Carbon|null $amended_at
 * @property string|null $amendment_reason
 * @property Carbon|null $guardian_notified_at
 * @property int|null $notification_id
 */
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'status', 'original_status', 'reason_code_id', 'minutes_late', 'note', 'amended_by',
        'amended_at', 'amendment_reason', 'guardian_notified_at', 'notification_id',
    ];

    protected $fillable = [
        'school_id', 'session_id', 'student_id', 'term_id', 'session_date', 'status',
        'original_status', 'reason_code_id', 'minutes_late', 'note', 'idempotency_key',
        'marked_by', 'marked_at', 'amended_by', 'amended_at', 'amendment_reason',
        'guardian_notified_at', 'notification_id',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'minutes_late' => 'integer',
            'marked_at' => 'datetime',
            'amended_at' => 'datetime',
            'guardian_notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AttendanceRecordFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'An attendance_records row may only change status (together with original_status/amended_by/amended_at/amendment_reason), or the guardian-notification fields, after creation — see AmendAttendanceRecordAction.',
                    ['dirty' => $illegal],
                );
            }

            if (in_array('status', $dirty, true) && ! in_array('amended_by', $dirty, true)) {
                throw new InvalidStateTransitionException(
                    'status may only change together with amended_by/amended_at/amendment_reason — use AmendAttendanceRecordAction (BR-ACA-04-008).',
                    ['dirty' => $dirty],
                );
            }
        });
    }

    /**
     * @return BelongsTo<AttendanceSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<AttendanceReasonCode, $this>
     */
    public function reasonCode(): BelongsTo
    {
        return $this->belongsTo(AttendanceReasonCode::class, 'reason_code_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
