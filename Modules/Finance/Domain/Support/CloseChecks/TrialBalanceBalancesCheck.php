<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Finance\Models\JournalLine;

/**
 * Book H3 FIN-12 §4, "Trial balance balances, per currency" — a
 * posting bug, stop everything. Registered into
 * `Modules\Core\Domain\Registry\CloseChecklistRegistry` (Book A
 * CORE-03's own real-time, non-persisted checklist engine) from
 * `FinanceServiceProvider::boot()` — a purely additive registration,
 * not a change to any already-gated posting action.
 */
final class TrialBalanceBalancesCheck implements CloseChecklistItem
{
    public function code(): string
    {
        return 'fin01_trial_balance_balances';
    }

    public function label(): string
    {
        return 'Trial balance balances, per currency';
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
        $totals = JournalLine::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->selectRaw('currency, direction, SUM(amount_minor) as total')
            ->groupBy('currency', 'direction')
            ->get()
            ->groupBy('currency');

        $unbalanced = [];

        foreach ($totals as $currency => $rows) {
            $debits = (int) ($rows->firstWhere('direction', 'DR')->total ?? 0);
            $credits = (int) ($rows->firstWhere('direction', 'CR')->total ?? 0);

            if ($debits !== $credits) {
                $unbalanced[$currency] = ['debits_minor' => $debits, 'credits_minor' => $credits];
            }
        }

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $unbalanced === [],
            blocking: $this->isBlocking(),
            message: $unbalanced === [] ? 'Every currency balances.' : 'One or more currencies do not balance.',
            details: $unbalanced,
        );
    }
}
