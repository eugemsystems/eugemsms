<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Finance\Models\TillSession;

/**
 * Book H3 FIN-12 §4, "All till sessions closed" — cash unaccounted.
 * See `TrialBalanceBalancesCheck`'s own docblock for the registration
 * pattern this mirrors.
 */
final class AllTillSessionsClosedCheck implements CloseChecklistItem
{
    public function code(): string
    {
        return 'fin04_all_till_sessions_closed';
    }

    public function label(): string
    {
        return 'All till sessions closed';
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
        $open = TillSession::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('status', 'open')
            ->get(['id']);

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $open->isEmpty(),
            blocking: $this->isBlocking(),
            message: $open->isEmpty() ? 'No open till sessions.' : "{$open->count()} till session(s) still open.",
            details: ['open_session_ids' => $open->pluck('id')->all()],
        );
    }
}
