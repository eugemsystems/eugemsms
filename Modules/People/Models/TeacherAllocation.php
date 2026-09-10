<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\SchoolClass;
use Modules\People\Database\Factories\TeacherAllocationFactory;

/**
 * Book C PPL-04 §2 ⭐ — consumed by ACA-02 and ACA-03. Immutable once
 * created except for the columns `EndTeacherAllocationAction` owns.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $staff_id
 * @property int $subject_id
 * @property int $class_id
 * @property string $role
 * @property int $weekly_periods
 * @property bool $is_class_teacher
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $status
 * @property int $allocated_by
 */
class TeacherAllocation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TeacherAllocationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['ends_on', 'status'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'staff_id', 'subject_id', 'class_id', 'role',
        'weekly_periods', 'is_class_teacher', 'starts_on', 'ends_on', 'status', 'allocated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_class_teacher' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TeacherAllocationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($notAllowed !== []) {
                throw new InvalidStateTransitionException(
                    'Only ends_on and status may change on an existing teacher allocation.',
                    ['dirty' => $notAllowed],
                );
            }
        });
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
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
}
