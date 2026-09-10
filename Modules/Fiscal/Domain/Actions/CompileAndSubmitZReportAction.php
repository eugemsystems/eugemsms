<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Fiscal\Domain\Contracts\FiscalGatewayDriver;
use Modules\Fiscal\Domain\Events\ZReportSubmitted;
use Modules\Fiscal\Domain\Exceptions\FdmsUnreachableException;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalReceipt;
use Modules\Fiscal\Models\FiscalZReport;

/**
 * ACT-CompileAndSubmitZReport (Book H3 FIN-13 §3/BR-FIN-13-018).
 * Compiles from this closed day's own accepted `FiscalReceipt` rows —
 * the totals are a real aggregation, not a placeholder. Submission
 * failure (FDMS unreachable) leaves the report `pending`; it can be
 * retried by calling this again, same as any other fiscal submission.
 */
final class CompileAndSubmitZReportAction extends Action
{
    public function __construct(
        private readonly FiscalGatewayDriver $driver,
    ) {}

    public function execute(int $fiscalDayId): FiscalZReport
    {
        $day = FiscalDay::findOrFail($fiscalDayId);

        if ($day->local_status !== 'closed') {
            throw new InvalidStateTransitionException("Fiscal day #{$day->id} must be closed before its Z-report compiles.", ['fiscal_day_id' => $day->id]);
        }

        $device = FiscalDevice::findOrFail($day->device_id);
        $receipts = FiscalReceipt::where('fiscal_day_id', $day->id)->where('status', 'accepted')->get();

        $totalsByCurrency = [];
        $totalsByTaxType = [];
        $totalsByPayment = [];

        foreach ($receipts as $receipt) {
            $totalsByCurrency[$receipt->receipt_currency] = ($totalsByCurrency[$receipt->receipt_currency] ?? 0) + $receipt->total_minor;

            foreach ($receipt->tax_breakdown as $taxType => $breakdown) {
                $totalsByTaxType[$taxType] = ($totalsByTaxType[$taxType] ?? 0) + (int) ($breakdown['tax_minor'] ?? 0);
            }

            foreach ($receipt->payment_methods as $method) {
                $totalsByPayment[$method] = ($totalsByPayment[$method] ?? 0) + 1;
            }
        }

        $zReport = FiscalZReport::updateOrCreate(
            ['fiscal_day_id' => $day->id],
            [
                'school_id' => $day->school_id,
                'device_id' => $device->id,
                'report_date' => $day->opened_at->toDateString(),
                'receipt_count' => $receipts->count(),
                'totals_by_currency' => $totalsByCurrency,
                'totals_by_tax_type' => $totalsByTaxType,
                'totals_by_payment' => $totalsByPayment,
                'status' => 'pending',
            ],
        );

        try {
            $this->driver->submitZReport($device, [
                'fiscal_day_number' => $day->fiscal_day_number,
                'totals_by_currency' => $totalsByCurrency,
                'totals_by_tax_type' => $totalsByTaxType,
                'totals_by_payment' => $totalsByPayment,
            ]);

            $zReport->update(['status' => 'submitted', 'submitted_at' => Carbon::now()]);
            event(new ZReportSubmitted($zReport->fresh()));
        } catch (FdmsUnreachableException) {
            // Stays `pending` — retried by calling this again, same as
            // any other fiscal submission.
        }

        return $zReport->fresh();
    }
}
