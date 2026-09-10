<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\ControlledStockLogEntry;

/**
 * ACT-ReceiveClinicStock (Book G BRD-06 §2/BR-BRD-06-013). Controlled
 * stock requires a witness on receipt too, not only administration.
 */
final class ReceiveClinicStockAction extends Action
{
    public function execute(int $clinicStockId, float $quantity, int $performedByUserId, ?int $witnessedByUserId = null, ?string $batchNumber = null, ?string $expiryDate = null): ClinicStock
    {
        $stock = ClinicStock::findOrFail($clinicStockId);

        if ($stock->is_controlled && ($witnessedByUserId === null || $witnessedByUserId === $performedByUserId)) {
            throw new InvalidStateTransitionException(
                "Controlled stock {$stock->name} requires a second, different staff member as witness on receipt (BR-BRD-06-013).",
                ['clinic_stock_id' => $stock->id],
            );
        }

        return $this->transaction(function () use ($stock, $quantity, $performedByUserId, $witnessedByUserId, $batchNumber, $expiryDate): ClinicStock {
            $balanceAfter = (float) $stock->quantity_on_hand + $quantity;

            $stock->update([
                'quantity_on_hand' => $balanceAfter,
                'batch_number' => $batchNumber ?? $stock->batch_number,
                'expiry_date' => $expiryDate ?? $stock->expiry_date,
            ]);

            if ($stock->is_controlled) {
                ControlledStockLogEntry::create([
                    'school_id' => $stock->school_id,
                    'clinic_stock_id' => $stock->id,
                    'action' => 'received',
                    'quantity' => $quantity,
                    'balance_after' => $balanceAfter,
                    'performed_by' => $performedByUserId,
                    'witnessed_by' => $witnessedByUserId,
                    'occurred_at' => Carbon::now(),
                ]);
            }

            return $stock;
        });
    }
}
