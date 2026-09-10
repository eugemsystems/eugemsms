<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Finance\Models\SuspenseItem;

/**
 * Book H3 FIN-12 §4, "Suspense balance zero or acknowledged" —
 * unidentified money. See `TrialBalanceBalancesCheck`'s own docblock
 * for the registration pattern this mirrors.
 */
final class SuspenseBalanceCheck implements CloseChecklistItem
{
    public function code(): string
    {
        return 'fin04_suspense_balance';
    }

    public function label(): string
    {
        return 'Suspense balance zero or acknowledged';
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
        $unidentified = SuspenseItem::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('status', 'unidentified')
            ->get();

        $total = (int) $unidentified->sum('amount_minor');

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $unidentified->isEmpty(),
            blocking: $this->isBlocking(),
            message: $unidentified->isEmpty() ? 'No unidentified suspense items.' : "{$unidentified->count()} unidentified suspense item(s) totalling {$total} minor units.",
            details: ['count' => $unidentified->count(), 'total_minor' => $total],
        );
    }
}
