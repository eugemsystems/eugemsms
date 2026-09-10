<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * Book H3 FIN-12 §4, "Payroll posted and returns prepared" 🇿🇼. A run
 * still in `computing`/`preview`/`approved` for this term is not yet
 * posted; a posted run with no matching `p2_paye` return means
 * `PrepareStatutoryReturnsAction` — auto-called from posting — never
 * ran, which should not be reachable but is worth catching for real
 * rather than assumed.
 */
final class PayrollPostedAndReturnsPreparedCheck implements CloseChecklistItem
{
    public function code(): string
    {
        return 'ppl05_payroll_posted_and_returns_prepared';
    }

    public function label(): string
    {
        return 'Payroll posted and returns prepared';
    }

    public function appliesTo(): PeriodType
    {
        return PeriodType::Financial;
    }

    public function isBlocking(): bool
    {
        return true;
    }

    public function check(Term $term): ChecklistItemResult
    {
        $runs = PayrollRun::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('term_id', $term->id)
            ->get();

        if ($runs->isEmpty()) {
            return new ChecklistItemResult(
                code: $this->code(), label: $this->label(), passed: true, blocking: $this->isBlocking(),
                message: 'No payroll runs for this term.',
            );
        }

        $unposted = $runs->whereNotIn('status', ['posted', 'paid']);

        if ($unposted->isNotEmpty()) {
            return new ChecklistItemResult(
                code: $this->code(), label: $this->label(), passed: false, blocking: $this->isBlocking(),
                message: "{$unposted->count()} payroll run(s) for this term are not yet posted.",
                details: ['unposted_run_ids' => $unposted->pluck('id')->all()],
            );
        }

        $periodMonths = $runs->pluck('period_month')->unique();
        $missingReturns = $periodMonths->filter(fn (string $month): bool => ! StatutoryReturn::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('return_type', 'p2_paye')
            ->where('period_reference', $month)
            ->exists());

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $missingReturns->isEmpty(),
            blocking: $this->isBlocking(),
            message: $missingReturns->isEmpty() ? 'Every posted run has its statutory returns prepared.' : 'One or more posted runs have no P2 return prepared.',
            details: ['months_missing_p2' => $missingReturns->values()->all()],
        );
    }
}
