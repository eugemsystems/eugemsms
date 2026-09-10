<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Listeners;

use Modules\Stores\Domain\Actions\ReleaseCommitmentAction;
use Modules\Stores\Domain\Events\InvoiceMatched;

/**
 * Book H1 FIN-11 §6 ⭐/BR-FIN-11-005/AC-FIN-11-002 — the real consumer
 * of `FIN-08`'s `InvoiceMatched`. Releases only the slice of the
 * commitment this specific invoice actually accounts for — the sum
 * of its own lines tied back to a PO line — never the whole
 * outstanding balance, since a partial delivery leaves a real
 * residual commitment open on the order (BR-FIN-11-005's own
 * "residual commitment stays open").
 */
final class ReleaseBudgetCommitmentOnInvoiceMatchedListener
{
    public function __construct(
        private readonly ReleaseCommitmentAction $releaseCommitment,
    ) {}

    public function handle(InvoiceMatched $event): void
    {
        $invoice = $event->invoice;

        if ($invoice->purchase_order_id === null) {
            return;
        }

        $releaseMinor = (int) $invoice->lines()->whereNotNull('po_line_id')->sum('line_total_minor');

        if ($releaseMinor <= 0) {
            return;
        }

        $this->releaseCommitment->execute('purchase_order', $invoice->purchase_order_id, $releaseMinor);
    }
}
