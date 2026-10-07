<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\MarkAmendmentRequestFactory;
use Modules\Academic\Domain\Actions\ApplyMarkAmendmentAction;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\ApprovalRequest;
use Modules\People\Models\Student;

/**
 * Book D ACA-05 §4/BR-ACA-05-010 — the `Approvable` request a
 * `published` mark's amendment waits in. `AmendMarkAction` itself
 * refuses to touch a published mark directly; this is the only route
 * in (`RequestMarkAmendmentAction` creates it, `onApproved()` is the
 * only caller that applies it, via `ApplyMarkAmendmentAction` — see
 * that action's own docblock for why `AmendMarkAction` itself refuses
 * a published assessment unconditionally).
 *
 * @property int $id
 * @property int $school_id
 * @property int $assessment_id
 * @property int $student_id
 * @property string|null $new_raw_mark
 * @property bool $new_is_absent
 * @property string $change_reason
 * @property int $requested_by
 * @property string $status
 * @property int|null $approval_request_id
 * @property Carbon|null $applied_at
 */
class MarkAmendmentRequest extends Model implements Approvable
{
    use BelongsToSchool;

    /** @use HasFactory<MarkAmendmentRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'assessment_id', 'student_id', 'new_raw_mark', 'new_is_absent',
        'change_reason', 'requested_by', 'status', 'approval_request_id', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'new_is_absent' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MarkAmendmentRequestFactory::new();
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvableType(): string
    {
        return 'mark_amendment';
    }

    public function approvalTitle(): string
    {
        $assessment = $this->assessment;

        return "Mark amendment — {$assessment?->title} for student #{$this->student_id}";
    }

    public function approvalSummary(): ?string
    {
        return $this->change_reason;
    }

    public function approvalAmount(): ?Money
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalPayload(): array
    {
        return [
            'assessment_id' => $this->assessment_id,
            'student_id' => $this->student_id,
            'new_raw_mark' => $this->new_raw_mark,
            'new_is_absent' => $this->new_is_absent,
        ];
    }

    /**
     * BR-ACA-05-010: this is the only place a published mark's
     * underlying `AssessmentMark` ever actually changes — the pending
     * row above is never itself trusted, same discipline as every
     * rebuild-only/apply-on-approval record in this codebase.
     */
    public function onApproved(ApprovalRequest $request): void
    {
        app(ApplyMarkAmendmentAction::class)->execute(new AmendMarkData(
            assessmentId: $this->assessment_id,
            studentId: $this->student_id,
            changedByUserId: $this->requested_by,
            changeReason: $this->change_reason,
            rawMark: $this->new_raw_mark === null ? null : (float) $this->new_raw_mark,
            isAbsent: $this->new_is_absent,
        ));

        $this->update(['status' => 'approved', 'applied_at' => now()]);
    }

    public function onRejected(ApprovalRequest $request): void
    {
        $this->update(['status' => 'rejected']);
    }

    public function onReturned(ApprovalRequest $request): void
    {
        // Left pending — a return asks the requester to amend and
        // resubmit, same convention as DiscountAward's own onReturned().
    }
}
