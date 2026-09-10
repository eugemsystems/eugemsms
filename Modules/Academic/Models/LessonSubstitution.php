<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LessonSubstitutionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book E ACA-03 §2/§6/BR-ACA-03-016/017/018/019 — cover.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $timetable_slot_id
 * @property Carbon $substitution_date
 * @property int $absent_staff_id
 * @property int|null $cover_staff_id
 * @property string $reason
 * @property int|null $leave_request_id
 * @property int|null $venue_id
 * @property string $status
 * @property string|null $work_set
 * @property Carbon|null $notified_at
 * @property int|null $assigned_by
 */
class LessonSubstitution extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LessonSubstitutionFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'timetable_slot_id', 'substitution_date', 'absent_staff_id',
        'cover_staff_id', 'reason', 'leave_request_id', 'venue_id', 'status', 'work_set',
        'notified_at', 'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'substitution_date' => 'date',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LessonSubstitutionFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<TimetableSlot, $this>
     */
    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function absentStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'absent_staff_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function coverStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'cover_staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
