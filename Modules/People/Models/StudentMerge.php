<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Database\Factories\StudentMergeFactory;

/**
 * Book C PPL-01 BR-PPL-01-010's permanent merge record — append-only at the model level
 * (same pattern as `financial_audit_log`/`PeriodSnapshot`): nothing may ever update or
 * delete a row here, matching "it cannot be undone".
 *
 * @property int $id
 * @property int $school_id
 * @property int $surviving_student_id
 * @property int $merged_student_id
 * @property string $merged_student_admission_number
 * @property string|null $reason
 * @property int $merged_by
 * @property Carbon $merged_at
 */
class StudentMerge extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentMergeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'surviving_student_id', 'merged_student_id',
        'merged_student_admission_number', 'reason', 'merged_by', 'merged_at',
    ];

    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
        ];
    }

    /**
     * Application-level enforcement of the same guarantee the DB grants provide in
     * production, matching `PeriodSnapshot`'s own precedent (see that migration's note
     * on the real `REVOKE` statement being an ops/deployment step, not a migration).
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('A student merge record is append-only and can never be changed.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('A student merge record is append-only and can never be deleted.');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentMergeFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function survivingStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'surviving_student_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function mergedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'merged_student_id');
    }
}
