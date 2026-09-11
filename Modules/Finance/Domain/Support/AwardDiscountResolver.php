<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\EnsureAutomaticAwardAction;
use Modules\Finance\Domain\Contracts\DiscountResolver;
use Modules\Finance\Domain\DataObjects\DiscountApplication;
use Modules\Finance\Domain\Events\AwardBudgetExceeded;
use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\People\Models\Student;

/**
 * Book K FIN-07 §3 ⭐/BR-FIN-07-001 ⭐ — the ONLY implementation of
 * `DiscountResolver`; `ComputeBillingRunAction` (`FIN-02`) calls
 * nothing else to reduce a fee line. Sponsor-funded awards
 * (BR-FIN-07-010) are deliberately excluded from the returned
 * collection — they never post a discount at all, they redirect the
 * guardian's liability to the sponsor instead (see
 * `GrantAwardAction`), so a fee line under a sponsor-funded award
 * shows its full, undiscounted gross.
 *
 * A budget check and its own application share one resolver call so
 * that two fee lines drawing on the same capped envelope within the
 * SAME billing run see each other's commitment — `committed_minor` is
 * incremented here, transactionally, the moment an application is
 * accepted, not deferred to whoever reads the collection back.
 */
final class AwardDiscountResolver implements DiscountResolver
{
    public function __construct(
        private readonly EnsureAutomaticAwardAction $ensureAutomaticAward,
    ) {}

    public function discountsFor(Student $student, FeeComponent $component, Money $grossAmount, Term $term): Collection
    {
        $this->ensureAutomaticAwardsCurrent($student, $term);

        $awards = DiscountAward::withoutGlobalScopes()
            ->with('scheme')
            ->where('school_id', $component->school_id)
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->where('academic_year_id', $term->academic_year_id)
            ->where(fn ($q) => $q->whereNull('term_id')->orWhere('term_id', $term->id))
            ->get()
            ->filter(fn (DiscountAward $award): bool => $award->appliesToComponent($component->id) && $award->scheme?->appliesToComponent($component->id))
            ->reject(fn (DiscountAward $award): bool => $award->isSponsorFunded());

        return $awards->map(function (DiscountAward $award) use ($grossAmount, $term): DiscountApplication {
            $scheme = $award->scheme;
            $discount = $award->computeDiscount($grossAmount);

            if ($discount->isZero()) {
                return DiscountApplication::apply($award, $discount, $scheme->contra_account_id);
            }

            $envelope = SchemeBudgetEnvelope::query()
                ->where('school_id', $scheme->school_id)
                ->where('scheme_id', $scheme->id)
                ->where('academic_year_id', $term->academic_year_id)
                ->first();

            if ($envelope !== null && $envelope->wouldExceed($discount->minor)) {
                $shortfall = $discount->minor - ($envelope->remainingMinor() ?? 0);
                event(new AwardBudgetExceeded($award, $envelope, $shortfall));

                return DiscountApplication::blocked($award, $discount->currency, 'budget_envelope_exceeded');
            }

            $envelope?->increment('committed_minor', $discount->minor);

            return DiscountApplication::apply($award, $discount, $scheme->contra_account_id);
        });
    }

    private function ensureAutomaticAwardsCurrent(Student $student, Term $term): void
    {
        $automaticSchemes = DiscountScheme::query()
            ->where('school_id', $student->school_id)
            ->where('scheme_type', 'automatic')
            ->where('is_active', true)
            ->get();

        foreach ($automaticSchemes as $scheme) {
            $this->ensureAutomaticAward->execute($student, $scheme, $term->academic_year_id);
        }
    }
}
