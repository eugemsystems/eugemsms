<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Models\SupplierInvoice;

/**
 * ACT-ComputeSupplierAging (Book H1 FIN-08 §6/BR-FIN-08-023). Buckets
 * are computed per currency — a USD and a ZWG balance are never
 * netted together into one meaningless number.
 */
final class ComputeSupplierAgingAction extends Action
{
    /**
     * @return Collection<string, array{currency: string, current: int, days_1_30: int, days_31_60: int, days_61_90: int, over_90: int, total: int}>
     */
    public function execute(int $supplierId, ?Carbon $asAt = null): Collection
    {
        $asAt ??= Carbon::now();
        $asAtDate = Carbon::parse($asAt->toDateString());

        $outstanding = SupplierInvoice::query()
            ->where('supplier_id', $supplierId)
            ->where('balance_minor', '>', 0)
            ->get();

        return $outstanding->groupBy('currency')->map(function (Collection $invoices, string $currency) use ($asAtDate): array {
            $buckets = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'over_90' => 0];

            foreach ($invoices as $invoice) {
                $signedDiff = $asAtDate->diffInDays(Carbon::parse($invoice->due_date->toDateString()), false);
                $daysOverdue = $signedDiff < 0 ? (int) abs($signedDiff) : 0;

                $bucket = match (true) {
                    $daysOverdue <= 0 => 'current',
                    $daysOverdue <= 30 => 'days_1_30',
                    $daysOverdue <= 60 => 'days_31_60',
                    $daysOverdue <= 90 => 'days_61_90',
                    default => 'over_90',
                };

                $buckets[$bucket] += (int) $invoice->balance_minor;
            }

            return [
                'currency' => $currency,
                ...$buckets,
                'total' => array_sum($buckets),
            ];
        });
    }
}
