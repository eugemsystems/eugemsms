<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;

/**
 * Book H3 FIN-12 §4, "No draft invoices" — unbilled income. See
 * `TrialBalanceBalancesCheck`'s own docblock for the registration
 * pattern this mirrors.
 */
final class NoDraftInvoicesCheck implements CloseChecklistItem
{
    public function code(): string
    {
        return 'fin03_no_draft_invoices';
    }

    public function label(): string
    {
        return 'No draft invoices';
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
        $drafts = Invoice::withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('term_id', $term->id)
            ->where('status', 'draft')
            ->get(['id']);

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $drafts->isEmpty(),
            blocking: $this->isBlocking(),
            message: $drafts->isEmpty() ? 'No draft invoices for this term.' : "{$drafts->count()} draft invoice(s) for this term.",
            details: ['draft_invoice_ids' => $drafts->pluck('id')->all()],
        );
    }
}
