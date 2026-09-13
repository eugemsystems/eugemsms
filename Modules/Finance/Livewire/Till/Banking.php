<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Till;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\GenerateDailyBankingReportAction;
use Modules\Finance\Domain\DataObjects\GenerateDailyBankingReportData;

/**
 * `Finance\Till\Banking` (Book B FIN-04 §5, `finance.till.view`). A
 * daily banking sheet built straight from `receipts`/`receipt_tenders`
 * via `GenerateDailyBankingReportAction` — `till_sessions`' own
 * `totals_by_tender`/`totals_by_currency` columns are declared but
 * nothing writes to them, so this reads the same ledger-adjacent
 * source of truth every other FIN-04 total in this pass uses rather
 * than trusting an always-empty cache.
 */
#[Title('Daily banking')]
#[Layout('layouts.app')]
final class Banking extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $date;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.till.view');
        $this->date = now()->toDateString();
    }

    public function render(): View
    {
        $report = app(GenerateDailyBankingReportAction::class)->execute(new GenerateDailyBankingReportData(
            schoolId: $this->school->id,
            date: $this->date,
        ));

        return view('finance::till.banking', [
            'tenderTotals' => $report->tenderTotals,
            'receiptCount' => $report->receiptCount,
        ]);
    }
}
