<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\GoodsReceivedNote;
use Modules\Stores\Models\GrnLine;
use Modules\Stores\Models\Supplier;

/**
 * ACT-RecalculateSupplierPerformance (Book H1 FIN-08 §6/BR-FIN-08-025).
 * Computed from this supplier's own real `goods_received_notes`/
 * `grn_lines` history — on-time is measured against the purchase
 * order's `expected_delivery`, never against the order date, and a
 * supplier with no delivery history yet gets `null` ratings rather
 * than a fabricated 100%.
 */
final class RecalculateSupplierPerformanceAction extends Action
{
    public function execute(int $supplierId): Supplier
    {
        $supplier = Supplier::findOrFail($supplierId);

        $grns = GoodsReceivedNote::with('purchaseOrder')
            ->where('supplier_id', $supplierId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereNotNull('expected_delivery'))
            ->get();

        $onTimePct = null;

        if ($grns->isNotEmpty()) {
            $onTimeCount = $grns->filter(fn (GoodsReceivedNote $grn): bool => $grn->received_on->lessThanOrEqualTo($grn->purchaseOrder->expected_delivery))->count();
            $onTimePct = round($onTimeCount / $grns->count() * 100, 2);
        }

        $totals = GrnLine::whereIn('grn_id', GoodsReceivedNote::where('supplier_id', $supplierId)->pluck('id'))
            ->selectRaw('SUM(quantity_delivered) as delivered, SUM(quantity_rejected) as rejected')
            ->first();

        $delivered = (float) ($totals?->getAttribute('delivered') ?? 0);
        $rejected = (float) ($totals?->getAttribute('rejected') ?? 0);

        $rejectionPct = $delivered > 0 ? round($rejected / $delivered * 100, 2) : null;

        return $this->transaction(fn (): Supplier => tap($supplier)->update([
            'on_time_delivery_pct' => $onTimePct,
            'quality_rejection_pct' => $rejectionPct,
            'rating' => $onTimePct !== null && $rejectionPct !== null
                ? round(min(5.0, max(0.0, ($onTimePct - $rejectionPct * 2) / 20)), 2)
                : null,
            'last_evaluated_at' => Carbon::now(),
        ]));
    }
}
