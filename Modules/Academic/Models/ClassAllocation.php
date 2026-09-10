<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ClassAllocationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §2/BR-ACA-02-014. A form class per term, distinct from
 * `TeachingGroup`. Mid-term movement supersedes: the prior active row's
 * `status`/`effective_to` change, a new row is inserted — never an edit
 * of `class_id` on an existing row — so history stays queryable.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $class_id
 * @property string $allocation_type
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property int $allocated_by
 * @property int|null $confirmed_by
 * @property string|null $notes
 */
class ClassAllocation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ClassAllocationFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['status', 'effective_to', 'confirmed_by', 'notes'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'class_id', 'allocation_type',
        'effective_from', 'effective_to', 'status', 'allocated_by', 'confirmed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ClassAllocationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A class_allocations row may only change status, effective_to, confirmed_by, or notes after creation — a class movement supersedes with a new row (BR-ACA-02-014).',
                    ['dirty' => $illegal],
                );
            }
        });
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
