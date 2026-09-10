<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * ACT-ConfirmTokenCredit (Book H2 OPS-04 §3 ⭐/BR-OPS-04-001). The
 * staff member who loaded the token at the meter confirms it here —
 * only then does the meter's own running balance move.
 */
final class ConfirmTokenCreditAction extends Action
{
    public function execute(int $tokenPurchaseId, int $creditedByUserId): PrepaidTokenPurchase
    {
        $purchase = PrepaidTokenPurchase::findOrFail($tokenPurchaseId);

        if ($purchase->status !== 'purchased') {
            throw new InvalidStateTransitionException(
                "Token purchase #{$purchase->id} must be purchased to confirm credit (currently {$purchase->status}).",
                ['token_purchase_id' => $purchase->id, 'status' => $purchase->status],
            );
        }

        return $this->transaction(function () use ($purchase, $creditedByUserId): PrepaidTokenPurchase {
            $purchase->update([
                'credited_at' => Carbon::now(),
                'credited_by' => $creditedByUserId,
                'credit_confirmed' => true,
                'status' => 'credited',
            ]);

            $meter = Meter::findOrFail($purchase->meter_id);
            $meter->update([
                'current_balance_units' => (float) $meter->current_balance_units + (float) $purchase->units_purchased,
            ]);

            return $purchase;
        });
    }
}
