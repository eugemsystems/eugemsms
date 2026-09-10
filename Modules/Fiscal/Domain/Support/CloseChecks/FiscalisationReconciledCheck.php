<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Support\CloseChecks;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;
use Modules\Fiscal\Domain\Actions\ReconcileFiscalisationAction;

/**
 * Book H3 FIN-12 §4, "Fiscalisation reconciled" 🇿🇼 — compliance
 * exposure. Reuses the real `ReconcileFiscalisationAction` (FIN-13,
 * this same book) directly rather than re-deriving the exception set
 * — that action IS the reconciliation ledger, per its own docblock.
 */
final class FiscalisationReconciledCheck implements CloseChecklistItem
{
    public function __construct(
        private readonly ReconcileFiscalisationAction $reconcile,
    ) {}

    public function code(): string
    {
        return 'fin13_fiscalisation_reconciled';
    }

    public function label(): string
    {
        return 'Fiscalisation reconciled';
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
        $unreconciled = $this->reconcile->execute($term->school_id);

        return new ChecklistItemResult(
            code: $this->code(),
            label: $this->label(),
            passed: $unreconciled->isEmpty(),
            blocking: $this->isBlocking(),
            message: $unreconciled->isEmpty() ? 'Every fiscalisable receipt is accepted by FDMS.' : "{$unreconciled->count()} fiscal receipt(s) not yet accepted beyond the reconciliation window.",
            details: ['unreconciled_count' => $unreconciled->count()],
        );
    }
}
