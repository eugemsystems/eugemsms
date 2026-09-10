<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\TeachingGroupFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book D ACA-02 §2/BR-ACA-02-012/013. A set for one subject,
 * independent of form class. `current_count` is a cached counter kept
 * in step with `TeachingGroupMember` rows by
 * `AssignToTeachingGroupAction`/`RemoveFromTeachingGroupAction` — the
 * membership rows, not this column, are authoritative.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $subject_id
 * @property int $grade_level_id
 * @property string $code
 * @property string $name
 * @property string|null $set_level
 * @property int|null $teacher_staff_id
 * @property int|null $room_id
 * @property int|null $capacity
 * @property int $current_count
 * @property bool $is_active
 */
class TeachingGroup extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TeachingGroupFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'subject_id', 'grade_level_id', 'code',
        'name', 'set_level', 'teacher_staff_id', 'room_id', 'capacity', 'current_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'current_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TeachingGroupFactory::new();
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
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'teacher_staff_id');
    }

    /**
     * @return HasMany<TeachingGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeachingGroupMember::class);
    }

    public function hasCapacityFor(int $additional = 1): bool
    {
        return $this->capacity === null || ($this->current_count + $additional) <= $this->capacity;
    }
}
