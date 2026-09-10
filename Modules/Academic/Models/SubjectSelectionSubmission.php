<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\SubjectSelectionSubmissionFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §2/BR-ACA-02-015/016. The option-choice workflow for
 * Form 3/Lower 6 entry (§6 `Academic\Selection\Form`). Created directly
 * at `status=submitted` — the spec's own screen/API split shows no
 * separate persisted `draft` stage, only a client-side form before
 * submit. `ALLOWED_TRANSITIONS` mirrors `StaffDisciplinaryCase`'s
 * explicit stage-map guard.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $student_id
 * @property int $grade_level_id
 * @property int|null $pathway_id
 * @property array<int, int> $selected_subject_ids
 * @property array<int, int>|null $reserve_subject_ids
 * @property array<string, mixed>|null $validation_result
 * @property int|null $indicative_fee_minor
 * @property string|null $indicative_fee_currency
 * @property string $status
 * @property int|null $submitted_by
 * @property int|null $guardian_approved_by
 * @property int|null $school_approved_by
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $allocated_at
 */
class SubjectSelectionSubmission extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectSelectionSubmissionFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * @var array<string, array<int, string>>
     */
    public const array ALLOWED_TRANSITIONS = [
        'submitted' => ['guardian_approved', 'school_approved', 'rejected'],
        'guardian_approved' => ['school_approved', 'rejected'],
        'school_approved' => ['allocated'],
    ];

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'status', 'validation_result', 'indicative_fee_minor', 'indicative_fee_currency',
        'guardian_approved_by', 'school_approved_by', 'rejection_reason', 'approved_at', 'allocated_at',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'student_id', 'grade_level_id', 'pathway_id',
        'selected_subject_ids', 'reserve_subject_ids', 'validation_result', 'indicative_fee_minor',
        'indicative_fee_currency', 'status', 'submitted_by', 'guardian_approved_by',
        'school_approved_by', 'rejection_reason', 'submitted_at', 'approved_at', 'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_subject_ids' => 'array',
            'reserve_subject_ids' => 'array',
            'validation_result' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'allocated_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectSelectionSubmissionFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A subject_selection_submissions row may only change status, validation_result, indicative_fee_minor/currency, the approval/rejection fields, or approved_at/allocated_at after creation.',
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * @return BelongsTo<Pathway, $this>
     */
    public function pathway(): BelongsTo
    {
        return $this->belongsTo(Pathway::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assertTransitionAllowed(string $to): void
    {
        if (! in_array($to, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true)) {
            throw new InvalidStateTransitionException(
                "subject_selection_submissions cannot move from '{$this->status}' to '{$to}'.",
                ['from' => $this->status, 'to' => $to],
            );
        }
    }
}
