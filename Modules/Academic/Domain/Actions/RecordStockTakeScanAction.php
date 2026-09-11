<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RecordStockTakeScanData;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

final class RecordStockTakeScanAction extends Action
{
    public function execute(RecordStockTakeScanData $data): LibraryStockTake
    {
        $stockTake = LibraryStockTake::findOrFail($data->stockTakeId);

        if ($stockTake->status !== 'in_progress') {
            throw new InvalidStateTransitionException(
                "Stock-take #{$stockTake->id} in [{$stockTake->status}] can no longer record scans.",
                ['stock_take_id' => $stockTake->id, 'status' => $stockTake->status],
            );
        }

        return $this->transaction(function () use ($stockTake, $data): LibraryStockTake {
            $scanned = collect($stockTake->scanned_copy_ids ?? [])->push($data->copyId)->unique()->values()->all();

            $stockTake->update([
                'scanned_copy_ids' => $scanned,
                'scanned_count' => count($scanned),
            ]);

            return $stockTake->fresh();
        });
    }
}
