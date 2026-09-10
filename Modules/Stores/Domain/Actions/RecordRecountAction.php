<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\StockTakeLine;

/**
 * ACT-RecordRecount (Book H1 FIN-09 §7/BR-FIN-09-015). The recount
 * must be a different person from the original count — enforced here,
 * not left to convention.
 */
final class RecordRecountAction extends Action
{
    public function execute(int $stockTakeLineId, float $recountQuantity, int $recountedByUserId, ?int $unitCostMinor = null): StockTakeLine
    {
        $line = StockTakeLine::findOrFail($stockTakeLineId);

        if ($line->counted_by === $recountedByUserId) {
            throw ValidationException::withMessages([
                'recountedByUserId' => 'The recount must be performed by a different person from the original count (BR-FIN-09-015).',
            ]);
        }

        $variance = $recountQuantity - (float) $line->system_quantity;

        return $this->transaction(fn (): StockTakeLine => tap($line)->update([
            'recount_quantity' => $recountQuantity,
            'variance_quantity' => $variance,
            'variance_value_minor' => $unitCostMinor !== null ? (int) round($variance * $unitCostMinor) : null,
            'requires_recount' => false,
        ]));
    }
}
