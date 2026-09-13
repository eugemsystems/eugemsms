<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\GenerateCollectionsReportAction;
use Modules\Finance\Domain\DataObjects\GenerateCollectionsReportData;

/**
 * `Finance\Reports\Collections` (Book B FIN-04 §5, `finance.report.collections`).
 * Collections between two dates broken down by day, tender, currency,
 * and cashier, via `GenerateCollectionsReportAction` — built straight
 * from `receipts`/`receipt_tenders` since `till_sessions`' own totals
 * columns are never populated.
 */
#[Title('Collections')]
#[Layout('layouts.app')]
final class Collections extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $fromDate;

    public string $toDate;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.report.collections');
        $this->fromDate = now()->startOfMonth()->toDateString();
        $this->toDate = now()->toDateString();
    }

    public function render(): View
    {
        $report = app(GenerateCollectionsReportAction::class)->execute(new GenerateCollectionsReportData(
            schoolId: $this->school->id,
            fromDate: $this->fromDate,
            toDate: $this->toDate,
        ));

        return view('finance::reports.collections', [
            'byDay' => $report->byDay,
            'byTender' => $report->byTender,
            'byCashier' => $report->byCashier,
        ]);
    }
}
