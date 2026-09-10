<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AttendanceSessionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book D ACA-04 §2 — a markable occasion. `expected_count`/`present_count`/
 * etc. are denormalised roll-up counters kept in step by
 * `MarkAttendanceAction`, verified against `attendance_records` the same
 * way `TeachingGroup.current_count` is verified against its membership
 * rows. BR-ACA-04-018: `status` starts `pending` and is never inferred
 * to mean "everyone present".
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property Carbon $session_date
 * @property string $mode
 * @property int|null $class_id
 * @property int|null $teaching_group_id
 * @property int|null $subject_id
 * @property int|null $staff_id
 * @property int|null $period_number
 * @property int|null $timetable_slot_id
 * @property int $expected_count
 * @property int $present_count
 * @property int $absent_count
 * @property int $late_count
 * @property int $excused_count
 * @property string $status
 * @property int|null $marked_by
 * @property Carbon|null $marked_at
 * @property Carbon|null $locked_at
 * @property string|null $device_source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AttendanceSession extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceSessionFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'session_date', 'mode', 'class_id',
        'teaching_group_id', 'subject_id', 'staff_id', 'period_number', 'timetable_slot_id',
        'expected_count', 'present_count', 'absent_count', 'late_count', 'excused_count',
        'status', 'marked_by', 'marked_at', 'locked_at', 'device_source',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'expected_count' => 'integer',
            'present_count' => 'integer',
            'absent_count' => 'integer',
            'late_count' => 'integer',
            'excused_count' => 'integer',
            'marked_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AttendanceSessionFactory::new();
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
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<TeachingGroup, $this>
     */
    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'session_id');
    }
}
