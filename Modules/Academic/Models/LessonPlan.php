<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LessonPlanFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Staff;

/**
 * Book K ACA-11 §2/BR-ACA-11-001/003.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $scheme_of_work_id
 * @property int|null $timetable_slot_id
 * @property int $teacher_staff_id
 * @property Carbon $lesson_date
 * @property string $topic
 * @property string|null $objectives
 * @property string|null $activities
 * @property string|null $resources_needed
 * @property string|null $differentiation_notes
 * @property string $status
 * @property string|null $hod_comments
 */
class LessonPlan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LessonPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'scheme_of_work_id', 'timetable_slot_id', 'teacher_staff_id', 'lesson_date', 'topic',
        'objectives', 'activities', 'resources_needed', 'differentiation_notes', 'status', 'hod_comments',
    ];

    protected function casts(): array
    {
        return [
            'lesson_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LessonPlanFactory::new();
    }

    /**
     * @return BelongsTo<SchemeOfWork, $this>
     */
    public function schemeOfWork(): BelongsTo
    {
        return $this->belongsTo(SchemeOfWork::class);
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
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }
}
