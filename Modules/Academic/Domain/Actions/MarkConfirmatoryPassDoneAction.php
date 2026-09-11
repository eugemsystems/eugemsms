<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\MarkConfirmatoryPassDoneData;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-MarkConfirmatoryPassDone (Book K ACA-10 §4/BR-ACA-10-009). Marks
 * that a second, independent scanning walk-through has happened —
 * the gate `CompleteLibraryStockTakeAction` checks before it will
 * mark anything lost.
 */
final class MarkConfirmatoryPassDoneAction extends Action
{
    public function execute(MarkConfirmatoryPassDoneData $data): LibraryStockTake
    {
        $stockTake = LibraryStockTake::findOrFail($data->stockTakeId);

        if ($stockTake->status !== 'in_progress') {
            throw new InvalidStateTransitionException(
                "Stock-take #{$stockTake->id} in [{$stockTake->status}] cannot record a confirmatory pass.",
                ['stock_take_id' => $stockTake->id, 'status' => $stockTake->status],
            );
        }

        return $this->transaction(function () use ($stockTake): LibraryStockTake {
            $stockTake->update(['confirmatory_pass_done' => true]);

            return $stockTake->fresh();
        });
    }
}
