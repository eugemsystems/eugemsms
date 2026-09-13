<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CollectionsByCashierRow;
use Modules\Finance\Domain\DataObjects\CollectionsByDayRow;
use Modules\Finance\Domain\DataObjects\CollectionsReport;
use Modules\Finance\Domain\DataObjects\GenerateCollectionsReportData;
use Modules\Finance\Domain\DataObjects\TenderTotalRow;

/**
 * ACT-GenerateCollectionsReport (Book B FIN-04 §5, `finance.report.collections`).
 * Three breakdowns of the same window's receipted total — by day, by
 * tender, by cashier — each a plain `DB::table()` aggregate (not an
 * Eloquent-model-bound query) so the aggregated rows stay untyped
 * stdClass, matching `GenerateTrialBalanceAction`'s own pattern rather
 * than have Larastan try to type a `selectRaw()`-only column as a real
 * model property.
 */
final class GenerateCollectionsReportAction extends Action
{
    protected bool $transactional = false;

    public function execute(GenerateCollectionsReportData $data): CollectionsReport
    {
        // `effective_date` is a `date`-cast column that stores a full
        // datetime — a bare `toDate` string as the upper bound would
        // lexicographically exclude every row from `toDate` itself
        // (see .ai/rules/finance.md's note on this exact trap).
        $base = DB::table('receipts')
            ->where('receipts.school_id', $data->schoolId)
            ->where('receipts.status', 'posted')
            ->whereBetween('receipts.effective_date', [
                Carbon::parse($data->fromDate)->startOfDay(),
                Carbon::parse($data->toDate)->endOfDay(),
            ]);

        $byDay = (clone $base)
            ->selectRaw('effective_date, currency, sum(amount_minor) as total_minor')
            ->groupBy('effective_date', 'currency')
            ->orderBy('effective_date')
            ->get()
            ->map(fn (object $row): CollectionsByDayRow => new CollectionsByDayRow(
                date: (string) $row->effective_date,
                currency: (string) $row->currency,
                totalMinor: (int) $row->total_minor,
            ))
            ->all();

        $byTender = (clone $base)
            ->join('receipt_tenders', 'receipt_tenders.receipt_id', '=', 'receipts.id')
            ->selectRaw('receipt_tenders.tender_type, receipt_tenders.currency, sum(receipt_tenders.amount_minor) as total_minor')
            ->groupBy('receipt_tenders.tender_type', 'receipt_tenders.currency')
            ->orderBy('receipt_tenders.tender_type')
            ->get()
            ->map(fn (object $row): TenderTotalRow => new TenderTotalRow(
                tenderType: (string) $row->tender_type,
                currency: (string) $row->currency,
                totalMinor: (int) $row->total_minor,
            ))
            ->all();

        $byCashier = (clone $base)
            ->join('users', 'users.id', '=', 'receipts.received_by')
            ->selectRaw('receipts.received_by as cashier_id, users.name as cashier_name, receipts.currency, sum(receipts.amount_minor) as total_minor')
            ->groupBy('receipts.received_by', 'users.name', 'receipts.currency')
            ->orderBy('users.name')
            ->get()
            ->map(fn (object $row): CollectionsByCashierRow => new CollectionsByCashierRow(
                cashierId: (int) $row->cashier_id,
                cashierName: (string) $row->cashier_name,
                currency: (string) $row->currency,
                totalMinor: (int) $row->total_minor,
            ))
            ->all();

        return new CollectionsReport(
            byDay: $byDay,
            byTender: $byTender,
            byCashier: $byCashier,
        );
    }
}
