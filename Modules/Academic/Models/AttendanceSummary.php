<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\AttendanceSummaryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-04 §2/BR-ACA-04-011 — a cache, rebuilt by
 * `RebuildAttendanceSummaryAction`, never itself authoritative.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property string $scope
 * @property int|null $subject_id
 * @property int $sessions_expected
 * @property int $present_count
 * @property int $absent_authorised
 * @property int $absent_unauthorised
 * @property int $late_count
 * @property string|null $attendance_percent
 * @property int $consecutive_absent_max
 * @property bool $is_chronic_absentee
 * @property Carbon|null $rebuilt_at
 */
class AttendanceSummary extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AttendanceSummaryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'term_id', 'scope', 'subject_id', 'sessions_expected',
        'present_count', 'absent_authorised', 'absent_unauthorised', 'late_count',
        'attendance_percent', 'consecutive_absent_max', 'is_chronic_absentee', 'rebuilt_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_percent' => 'decimal:2',
            'is_chronic_absentee' => 'boolean',
            'rebuilt_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AttendanceSummaryFactory::new();
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
}
