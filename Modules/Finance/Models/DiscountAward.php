<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\Term;
use Modules\Finance\Database\Factories\DiscountAwardFactory;
use Modules\Finance\Domain\Actions\ActivateSponsorAwardLiabilityAction;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * Book K FIN-07 §2 ⭐ — the granted instance; what `FIN-02`'s
 * `DiscountResolver` reads. Table name `discount_awards`, not the
 * spec's literal `awards` — see the owning migration's docblock for
 * why (a real collision with `Modules\Sport`'s own `awards` table).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $scheme_id
 * @property int $student_id
 * @property int|null $application_id
 * @property int $academic_year_id
 * @property int|null $term_id
 * @property array<int, int>|null $applies_to_components
 * @property string $award_method
 * @property string|null $award_percent
 * @property int|null $award_amount_minor
 * @property string|null $currency
 * @property int|null $sponsor_guardian_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property string|null $condition_note
 * @property Carbon|null $condition_last_checked_at
 * @property bool|null $condition_met
 * @property int|null $granted_by
 * @property int|null $approval_request_id
 * @property string|null $revoked_reason
 */
class DiscountAward extends Model implements Approvable
{
    use BelongsToSchool;

    /** @use HasFactory<DiscountAwardFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'discount_awards';

    protected $fillable = [
        'school_id', 'scheme_id', 'student_id', 'application_id', 'academic_year_id', 'term_id',
        'applies_to_components', 'award_method', 'award_percent', 'award_amount_minor', 'currency',
        'sponsor_guardian_id', 'effective_from', 'effective_to', 'status', 'condition_note',
        'condition_last_checked_at', 'condition_met', 'granted_by', 'approval_request_id', 'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_components' => 'array',
            'award_percent' => 'decimal:2',
            'award_amount_minor' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'condition_last_checked_at' => 'datetime',
            'condition_met' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DiscountAwardFactory::new();
    }

    /**
     * @return BelongsTo<DiscountScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(DiscountScheme::class, 'scheme_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<ScholarshipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class);
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
     * @return BelongsTo<Guardian, $this>
     */
    public function sponsorGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'sponsor_guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isSponsorFunded(): bool
    {
        return $this->sponsor_guardian_id !== null;
    }

    public function appliesToComponent(int $componentId): bool
    {
        return $this->applies_to_components === null || in_array($componentId, $this->applies_to_components, true);
    }

    /**
     * Book K FIN-07 §3 ⭐ — `computeDiscount`. Never returns more than
     * the gross itself; a fixed-amount award larger than the
     * component's own gross simply caps at the gross (a discount can
     * never make a fee line negative).
     */
    public function computeDiscount(Money $gross): Money
    {
        $discount = match ($this->award_method) {
            'percentage' => $gross->multiplyBy(bcdiv((string) ($this->award_percent ?? '0'), '100', 10)),
            'fixed_amount' => Money::of($this->award_amount_minor ?? 0, $gross->currency),
            default => Money::zero($gross->currency),
        };

        return $discount->compareTo($gross) > 0 ? $gross : $discount;
    }

    public function approvableType(): string
    {
        return 'discount_award';
    }

    public function approvalTitle(): string
    {
        return "Discount award — {$this->scheme?->name} for student #{$this->student_id}";
    }

    public function approvalSummary(): ?string
    {
        return $this->scheme?->name;
    }

    public function approvalAmount(): ?Money
    {
        if ($this->currency === null) {
            return null;
        }

        $currency = Currency::from($this->currency);

        return $this->award_method === 'fixed_amount'
            ? Money::of($this->award_amount_minor ?? 0, $currency)
            : Money::zero($currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalPayload(): array
    {
        return [
            'scheme_id' => $this->scheme_id,
            'student_id' => $this->student_id,
            'award_method' => $this->award_method,
            'award_percent' => $this->award_percent,
            'award_amount_minor' => $this->award_amount_minor,
            'is_sponsor_funded' => $this->isSponsorFunded(),
        ];
    }

    public function onApproved(ApprovalRequest $request): void
    {
        $this->update(['status' => 'active']);

        if ($this->isSponsorFunded()) {
            app(ActivateSponsorAwardLiabilityAction::class)->execute($this->fresh());
        }
    }

    public function onRejected(ApprovalRequest $request): void
    {
        $this->update(['status' => 'revoked', 'revoked_reason' => 'Approval rejected.']);
    }

    public function onReturned(ApprovalRequest $request): void
    {
        // Left in its current (pending) status — a return asks the
        // requester to amend and resubmit, not a status change here.
    }
}
