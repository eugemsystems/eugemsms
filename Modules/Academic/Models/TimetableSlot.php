<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\TimetableSlotFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book E ACA-03 §2/BR-ACA-03-009 — one lesson in one slot.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $timetable_id
 * @property int $term_id
 * @property int $period_slot_id
 * @property int $cycle_day
 * @property int $period_number
 * @property int $subject_id
 * @property int|null $class_id
 * @property int|null $teaching_group_id
 * @property int $staff_id
 * @property int|null $co_staff_id
 * @property int|null $venue_id
 * @property bool $is_double
 * @property int|null $double_partner_slot_id
 * @property bool $is_locked
 * @property string|null $notes
 */
class TimetableSlot extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TimetableSlotFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'timetable_id', 'term_id', 'period_slot_id', 'cycle_day', 'period_number',
        'subject_id', 'class_id', 'teaching_group_id', 'staff_id', 'co_staff_id', 'venue_id',
        'is_double', 'double_partner_slot_id', 'is_locked', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_double' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TimetableSlotFactory::new();
    }

    /**
     * @return BelongsTo<Timetable, $this>
     */
    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<PeriodSlot, $this>
     */
    public function periodSlot(): BelongsTo
    {
        return $this->belongsTo(PeriodSlot::class, 'period_slot_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
