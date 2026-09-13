<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\DailyBankingReport;
use Modules\Finance\Domain\DataObjects\GenerateDailyBankingReportData;
use Modules\Finance\Domain\DataObjects\TenderTotalRow;
use Modules\Finance\Models\Receipt;

/**
 * ACT-GenerateDailyBankingReport (Book B FIN-04 §5). `till_sessions`'
 * own `totals_by_tender`/`totals_by_currency` columns exist but are
 * never written to by any action in this pass — this report is built
 * straight from `receipts`/`receipt_tenders` instead, the same
 * ledger-adjacent source of truth every other FIN-04 total uses,
 * rather than trusting an always-empty cache. A plain `DB::table()`
 * join (not an Eloquent-model-bound query) keeps the aggregated rows
 * untyped stdClass, matching `GenerateTrialBalanceAction`'s own
 * pattern — avoids Larastan trying (and failing) to type a
 * `selectRaw()`-only column as a real model property.
 */
final class GenerateDailyBankingReportAction extends Action
{
    protected bool $transactional = false;

    public function execute(GenerateDailyBankingReportData $data): DailyBankingReport
    {
        $rows = DB::table('receipt_tenders')
            ->join('receipts', 'receipts.id', '=', 'receipt_tenders.receipt_id')
            ->where('receipts.school_id', $data->schoolId)
            ->whereDate('receipts.effective_date', $data->date)
            ->where('receipts.status', 'posted')
            ->selectRaw('receipt_tenders.tender_type, receipt_tenders.currency, sum(receipt_tenders.amount_minor) as total_minor')
            ->groupBy('receipt_tenders.tender_type', 'receipt_tenders.currency')
            ->orderBy('receipt_tenders.tender_type')
            ->get();

        $tenderTotals = $rows->map(fn (object $row): TenderTotalRow => new TenderTotalRow(
            tenderType: (string) $row->tender_type,
            currency: (string) $row->currency,
            totalMinor: (int) $row->total_minor,
        ))->all();

        $receiptCount = Receipt::query()
            ->where('school_id', $data->schoolId)
            ->whereDate('effective_date', $data->date)
            ->where('status', 'posted')
            ->count();

        return new DailyBankingReport(
            tenderTotals: $tenderTotals,
            receiptCount: $receiptCount,
        );
    }
}
