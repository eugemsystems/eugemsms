<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ProjectAmendmentRequestFactory;
use Modules\Academic\Domain\Actions\ApplyVerifiedProjectAmendmentAction;
use Modules\Academic\Domain\DataObjects\AmendVerifiedProjectData;
use Modules\Academic\Domain\DataObjects\CriterionMarkInput;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\ApprovalRequest;

/**
 * Book E ACA-06 §6 — the `Approvable` request a `verified` project's
 * amendment waits in, mirroring `MarkAmendmentRequest` (Book D
 * ACA-05). `AmendVerifiedProjectAction` was removed rather than kept
 * as a gate that always throws: unlike a mark, a project has no
 * "not yet verified, amend directly" path at all — amending one
 * always requires approval, so `RequestVerifiedProjectAmendmentAction`
 * is the only entry point.
 *
 * @property int $id
 * @property int $school_id
 * @property int $learner_project_id
 * @property array<string, float> $new_criterion_marks
 * @property string $change_reason
 * @property int $requested_by
 * @property string $status
 * @property int|null $approval_request_id
 * @property Carbon|null $applied_at
 */
class ProjectAmendmentRequest extends Model implements Approvable
{
    use BelongsToSchool;

    /** @use HasFactory<ProjectAmendmentRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'learner_project_id', 'new_criterion_marks',
        'change_reason', 'requested_by', 'status', 'approval_request_id', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'new_criterion_marks' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProjectAmendmentRequestFactory::new();
    }

    /**
     * @return BelongsTo<LearnerProject, $this>
     */
    public function learnerProject(): BelongsTo
    {
        return $this->belongsTo(LearnerProject::class);
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
        return 'project_amendment';
    }

    public function approvalTitle(): string
    {
        return "Project amendment — learner project #{$this->learner_project_id}";
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
            'learner_project_id' => $this->learner_project_id,
            'new_criterion_marks' => $this->new_criterion_marks,
        ];
    }

    public function onApproved(ApprovalRequest $request): void
    {
        app(ApplyVerifiedProjectAmendmentAction::class)->execute(new AmendVerifiedProjectData(
            learnerProjectId: $this->learner_project_id,
            changedByUserId: $this->requested_by,
            changeReason: $this->change_reason,
            criterionMarks: array_map(
                fn (string $criterion, float $mark): CriterionMarkInput => new CriterionMarkInput($criterion, $mark),
                array_keys($this->new_criterion_marks),
                array_values($this->new_criterion_marks),
            ),
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
