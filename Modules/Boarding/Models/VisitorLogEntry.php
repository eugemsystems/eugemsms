<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\VisitorLogEntryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * Book F BRD-03 §2 — APPEND-ONLY. Maps to `visitor_logs` — named
 * `Entry` here since `VisitorLog` reads better as a concept than a
 * row. Signing out is the one legitimate post-creation update
 * (`signed_out_at`/`gate_staff_out`), never a delete.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $visitor_id
 * @property string $visit_purpose
 * @property int|null $host_staff_id
 * @property int|null $student_id
 * @property string|null $vehicle_registration
 * @property string|null $badge_number
 * @property Carbon $signed_in_at
 * @property Carbon|null $signed_out_at
 * @property int|null $expected_duration_mins
 * @property int $gate_staff_in
 * @property int|null $gate_staff_out
 * @property string|null $items_declared
 * @property bool $induction_completed
 * @property string|null $notes
 */
class VisitorLogEntry extends Model
{
    protected $table = 'visitor_logs';

    use BelongsToSchool;

    /** @use HasFactory<VisitorLogEntryFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['signed_out_at', 'gate_staff_out'];

    protected $fillable = [
        'school_id', 'visitor_id', 'visit_purpose', 'host_staff_id', 'student_id',
        'vehicle_registration', 'badge_number', 'signed_in_at', 'signed_out_at',
        'expected_duration_mins', 'gate_staff_in', 'gate_staff_out', 'items_declared',
        'induction_completed', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
            'signed_out_at' => 'datetime',
            'induction_completed' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VisitorLogEntryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A visitor_logs row may only record its sign-out after creation.',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('visitor_logs is append-only — an entry is never deleted.', []);
        });
    }

    /**
     * @return BelongsTo<Visitor, $this>
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function hostStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'host_staff_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gateStaffIn(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_staff_in');
    }
}
