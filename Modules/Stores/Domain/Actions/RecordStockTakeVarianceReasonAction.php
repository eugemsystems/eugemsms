<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\StockTakeLine;

/**
 * ACT-RecordStockTakeVarianceReason (Book H1 FIN-09 §7/BR-FIN-09-016).
 * A small gap-filling Action — the same precedent as ACA-01's own
 * catalogue gap and this book's own `CreateVerificationRoundAction`:
 * `ApproveStockTakeVarianceAction` requires every variant line to
 * carry a recorded `variance_reason` before approval, but no Action
 * anywhere ever wrote that single field; the admin UI pass's own
 * `StockTake\Variance` screen was found writing it directly
 * (`StockTakeLine::update()`), caught by `ActionPatternEnforcementTest`
 * (BR-GLOBAL-005 — no database write outside an Action). This is
 * deliberately the narrowest possible fix: set one field, no business
 * logic beyond what the migration already enforces.
 */
final class RecordStockTakeVarianceReasonAction extends Action
{
    public function execute(int $stockTakeLineId, ?string $reason): StockTakeLine
    {
        $line = StockTakeLine::findOrFail($stockTakeLineId);

        return $this->transaction(fn (): StockTakeLine => tap($line)->update([
            'variance_reason' => $reason,
        ]));
    }
}
