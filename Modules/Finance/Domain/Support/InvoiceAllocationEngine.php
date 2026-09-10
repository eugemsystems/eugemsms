<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\Receipt;
use Modules\Finance\Models\ReceiptAllocation;

/**
 * Book B FIN-04 §4 ⭐. Settles a receipt against a student's open
 * invoices, oldest-first / component-priority / a caller-supplied
 * manual order. Only ever auto-allocates within one currency
 * ("A receipt only auto-allocates to invoices in the same currency" —
 * §4's currency rule); cross-currency settlement is a deliberate,
 * manual act `FIN-06` owns and is not built here.
 *
 * A settled invoice's amount is distributed across its own lines by
 * each line's *original* `net_minor` share (via `Money::allocate()`),
 * not by a tracked per-line remaining balance — `invoice_lines` has no
 * such cache column. This is exact for an invoice settled in a single
 * pass (the common case) and a documented approximation for one paid
 * down across several receipts: the invoice's own `paid_minor`/
 * `balance_minor` stay exactly correct either way; only the
 * per-component GL attribution for a partially-paid, multi-component
 * invoice can drift slightly across receipts.
 */
final class InvoiceAllocationEngine
{
    /**
     * @param  array<int, int>|null  $manualInvoiceOrder  invoice ids in the order to settle, for AllocationStrategy::Manual
     * @return array{allocations: Collection<int, ReceiptAllocation>, leftoverMinor: int}
     */
    public function allocate(
        Receipt $receipt,
        int $studentId,
        int $availableMinor,
        string $currency,
        AllocationStrategy $strategy,
        int $allocatedByUserId,
        ?array $manualInvoiceOrder = null,
    ): array {
        $invoices = $this->orderedOpenInvoices($studentId, $currency, $strategy, $manualInvoiceOrder);

        $remaining = $availableMinor;
        $allocations = collect();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $settle = min($remaining, $invoice->balance_minor);

            if ($settle <= 0) {
                continue;
            }

            $allocations = $allocations->merge($this->settleInvoice($receipt, $invoice, $settle, $strategy, $allocatedByUserId));
            $remaining -= $settle;
        }

        return ['allocations' => $allocations, 'leftoverMinor' => $remaining];
    }

    /**
     * @return Collection<int, ReceiptAllocation>
     */
    private function settleInvoice(Receipt $receipt, Invoice $invoice, int $settleMinor, AllocationStrategy $strategy, int $allocatedByUserId): Collection
    {
        $lines = $invoice->lines;
        $ratios = $lines->mapWithKeys(fn ($line): array => [$line->id => max(1, $line->net_minor)])->all();
        $shares = Money::of($settleMinor, Currency::from($invoice->currency))->allocate($ratios);

        $rows = $lines->map(function ($line) use ($receipt, $invoice, $shares, $strategy, $allocatedByUserId): ReceiptAllocation {
            $share = $shares[$line->id]->minor;

            return ReceiptAllocation::create([
                'school_id' => $receipt->school_id,
                'receipt_id' => $receipt->id,
                'invoice_id' => $invoice->id,
                'invoice_line_id' => $line->id,
                'component_id' => $line->component_id,
                'amount_minor' => $share,
                'currency' => $invoice->currency,
                'allocation_method' => $strategy->value,
                'allocated_at' => Carbon::now(),
                'allocated_by' => $allocatedByUserId,
            ]);
        })->filter(fn (ReceiptAllocation $allocation): bool => $allocation->amount_minor > 0);

        $newBalance = $invoice->balance_minor - $settleMinor;

        $invoice->update([
            'paid_minor' => $invoice->paid_minor + $settleMinor,
            'balance_minor' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : 'partially_paid',
        ]);

        return $rows->values();
    }

    /**
     * @param  array<int, int>|null  $manualInvoiceOrder
     * @return Collection<int, Invoice>
     */
    private function orderedOpenInvoices(int $studentId, string $currency, AllocationStrategy $strategy, ?array $manualInvoiceOrder): Collection
    {
        $open = Invoice::query()
            ->where('student_id', $studentId)
            ->where('currency', $currency)
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('balance_minor', '>', 0)
            ->with('lines')
            ->get();

        if ($strategy === AllocationStrategy::Manual && $manualInvoiceOrder !== null) {
            return collect($manualInvoiceOrder)
                ->map(fn (int $id) => $open->firstWhere('id', $id))
                ->filter();
        }

        if ($strategy === AllocationStrategy::ComponentPriority) {
            return $open->sortBy(fn (Invoice $invoice): array => [
                $invoice->lines->min('allocation_priority') ?? 100,
                $invoice->due_date->timestamp,
            ])->values();
        }

        return $open->sortBy(fn (Invoice $invoice): int => (int) $invoice->due_date->timestamp)->values();
    }
}
