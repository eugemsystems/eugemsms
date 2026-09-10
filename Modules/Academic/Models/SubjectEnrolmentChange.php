<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\SubjectEnrolmentChangeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * Book D ACA-02 §2/BR-ACA-02-007. Append-only — `proration_factor` is
 * stored at change time and never recomputed (AC-ACA-02-003). Only the
 * billing-dispatch tracking columns may ever update, mirroring
 * `StudentAttributeChange`'s `rebilling_status`-only guard.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $term_id
 * @property int $subject_id
 * @property string $change_type
 * @property Carbon $effective_from
 * @property int|null $teaching_days_remaining
 * @property int|null $term_teaching_days
 * @property string|null $proration_factor
 * @property string|null $reason
 * @property int $changed_by
 * @property Carbon $changed_at
 * @property bool $billing_event_dispatched
 * @property string|null $billing_event_result
 * @property string|null $billing_reference
 */
class SubjectEnrolmentChange extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectEnrolmentChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = [
        'billing_event_dispatched', 'billing_event_result', 'billing_reference',
    ];

    protected $fillable = [
        'school_id', 'student_id', 'term_id', 'subject_id', 'change_type', 'effective_from',
        'teaching_days_remaining', 'term_teaching_days', 'proration_factor', 'reason',
        'changed_by', 'changed_at', 'billing_event_dispatched', 'billing_event_result',
        'billing_reference',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'proration_factor' => 'decimal:6',
            'changed_at' => 'datetime',
            'billing_event_dispatched' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectEnrolmentChangeFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'subject_enrolment_changes is append-only — only billing_event_dispatched, billing_event_result, and billing_reference may change after creation (BR-ACA-02-007).',
                    ['dirty' => $illegal],
                );
            }
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException(
                'subject_enrolment_changes rows are never deleted (BR-ACA-02-007).',
                [],
            );
        });
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
