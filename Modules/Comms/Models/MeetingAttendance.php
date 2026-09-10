<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\MeetingAttendanceFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book I COM-07 §3 ⭐/BR-COM-07-007/008. Raw, advisory join/leave data
 * — never itself an `ACA-04` attendance record; see
 * `Modules\Comms\Domain\Actions\PreviewAttendanceReconciliationAction`'s
 * own docblock for how it becomes one.
 *
 * @property int $id
 * @property int $school_id
 * @property int $meeting_id
 * @property string $participant_identifier
 * @property int|null $student_id
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property int|null $duration_seconds
 * @property string|null $match_confidence
 */
class MeetingAttendance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MeetingAttendanceFactory> */
    use HasFactory;

    protected $table = 'meeting_attendance';

    protected $fillable = [
        'school_id', 'meeting_id', 'participant_identifier', 'student_id',
        'joined_at', 'left_at', 'duration_seconds', 'match_confidence',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MeetingAttendanceFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isMatched(): bool
    {
        return $this->student_id !== null;
    }
}
