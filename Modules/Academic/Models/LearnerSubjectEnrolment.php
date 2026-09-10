<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LearnerSubjectEnrolmentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §2 ⭐/BR-ACA-02-001. THE billing source of truth for a
 * learner's subject count — see `SubjectEnrolmentQuery`, the only
 * sanctioned way to read it. Once created, only the drop/billing-status
 * fields ever change; a row is never deleted, only dropped
 * (`effective_to` set) — mirrors `Student`'s authorization-flag guard.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $subject_id
 * @property int|null $class_id
 * @property int|null $subject_group_id
 * @property string $enrolment_reason
 * @property string $status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property bool $is_billable
 * @property string $billing_status
 * @property int|null $fee_line_id
 * @property int|null $credit_note_id
 * @property int $added_by
 * @property Carbon $added_at
 * @property int|null $dropped_by
 * @property Carbon|null $dropped_at
 * @property string|null $drop_reason
 * @property int|null $approval_request_id
 */
class LearnerSubjectEnrolment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LearnerSubjectEnrolmentFactory> */
    use HasFactory;

    use HasUlid;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'status', 'effective_to', 'is_billable', 'billing_status', 'fee_line_id',
        'credit_note_id', 'dropped_by', 'dropped_at', 'drop_reason', 'approval_request_id',
    ];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'subject_id', 'class_id',
        'subject_group_id', 'enrolment_reason', 'status', 'effective_from', 'effective_to',
        'is_billable', 'billing_status', 'fee_line_id', 'credit_note_id', 'added_by',
        'added_at', 'dropped_by', 'dropped_at', 'drop_reason', 'approval_request_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_billable' => 'boolean',
            'added_at' => 'datetime',
            'dropped_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LearnerSubjectEnrolmentFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A learner_subject_enrolments row may only change status, effective_to, is_billable, billing_status, fee_line_id, credit_note_id, dropped_by, dropped_at, drop_reason, or approval_request_id after creation (BR-ACA-02-001/007).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException(
                'A learner_subject_enrolments row is never deleted, only dropped (effective_to set).',
                [],
            );
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
     * @return BelongsTo<SubjectGroup, $this>
     */
    public function subjectGroup(): BelongsTo
    {
        return $this->belongsTo(SubjectGroup::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * @return BelongsTo<ApprovalRequest, $this>
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function isBillableOn(Carbon $on): bool
    {
        if (! $this->is_billable) {
            return false;
        }

        if ($this->effective_from->greaterThan($on)) {
            return false;
        }

        return $this->effective_to === null || $this->effective_to->greaterThan($on);
    }
}
