<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Finance\Models\JournalLine;
use Modules\Reporting\Domain\DataObjects\GenerateAccountingExportData;
use Modules\Reporting\Domain\Events\AccountingExported;
use Modules\Reporting\Domain\Events\DuplicateAccountingExportAttempted;
use Modules\Reporting\Models\AccountingExport;

/**
 * ACT-GenerateAccountingExport (Book H3 FIN-12 §4/BR-FIN-12-014). A
 * plain CSV of every journal line in the range — "the target
 * system's own format" beyond generic CSV (a real QuickBooks/Sage/
 * Pastel column mapping) needs that product's own documented import
 * schema, which this pass doesn't have; `generic_csv` is the one
 * real format built. `period_from`/`period_to` on every prior export
 * for this school are checked first — an overlapping range is not
 * refused (a school may legitimately need to re-export), but fires
 * `DuplicateAccountingExportAttempted` so it's detectable, per the
 * rule's own wording.
 */
final class GenerateAccountingExportAction extends Action
{
    public function __construct(
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(GenerateAccountingExportData $data): AccountingExport
    {
        $overlapping = AccountingExport::where('school_id', $data->schoolId)
            ->where('period_from', '<=', $data->periodTo->toDateString())
            ->where('period_to', '>=', $data->periodFrom->toDateString())
            ->exists();

        if ($overlapping) {
            event(new DuplicateAccountingExportAttempted($data->schoolId, $data->periodFrom->toDateString(), $data->periodTo->toDateString()));
        }

        $lines = JournalLine::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->whereDate('effective_at', '>=', $data->periodFrom->toDateString())
            ->whereDate('effective_at', '<=', $data->periodTo->toDateString())
            ->with('account')
            ->orderBy('effective_at')
            ->get();

        $csv = "journal_id,effective_at,account_code,account_name,direction,amount_minor,currency,narration\n";

        foreach ($lines as $line) {
            $csv .= implode(',', [
                $line->journal_id,
                $line->effective_at->toDateString(),
                $line->account->code,
                str_replace(',', ' ', $line->account->name),
                $line->direction,
                $line->amount_minor,
                $line->currency,
                str_replace(',', ' ', (string) $line->narration),
            ])."\n";
        }

        $file = $this->uploadFile->execute(new UploadFileData(
            schoolId: $data->schoolId,
            category: 'accounting_export',
            contents: $csv,
            originalName: "accounting-export-{$data->periodFrom->toDateString()}-{$data->periodTo->toDateString()}.csv",
            uploadedByUserId: $data->exportedByUserId,
        ));

        $export = $this->transaction(fn (): AccountingExport => AccountingExport::create([
            'school_id' => $data->schoolId,
            'target_system' => $data->targetSystem,
            'period_from' => $data->periodFrom->toDateString(),
            'period_to' => $data->periodTo->toDateString(),
            'journal_count' => $lines->pluck('journal_id')->unique()->count(),
            'export_file_id' => $file->id,
            'exported_by' => $data->exportedByUserId,
            'exported_at' => Carbon::now(),
        ]));

        event(new AccountingExported($export));

        return $export;
    }
}
