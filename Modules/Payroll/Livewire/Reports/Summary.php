<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayrollRun;

/**
 * `Payroll\Reports\Summary` (Book H3 PPL-05 §6, `payroll.report.view`).
 * Named `Summary`, not the spec's own bare `Index` — `Reports/Index`
 * already exists at that exact relative path in `Modules\Farm`
 * (shipped in Book H2 OPS-03), which the standing duplicate-component-name
 * check in this pass's own instructions flagged before this file was
 * written; renamed here rather than touching Farm's earlier screen,
 * the same precedent `KitchenTransfers`/`Houses\Leaderboard` already
 * set in prior passes. Folds the spec's own "cost by cost centre,
 * headcount, statutory summary" into one read over already-posted
 * `PayrollRun` rows — no new Action, since every figure needed is
 * already stored on the run itself.
 */
#[Title('Payroll reports')]
#[Layout('layouts.app')]
final class Summary extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $periodMonth = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.report.view');

        $this->periodMonth = now()->format('Y-m');
    }

    public function render(): View
    {
        $runs = PayrollRun::where('school_id', $this->school->id)
            ->whereIn('status', ['posted', 'paid'])
            ->orderByDesc('period_month')
            ->limit(12)
            ->get();

        return view('payroll::reports.summary', [
            'runs' => $runs,
            'current' => $runs->firstWhere('period_month', $this->periodMonth),
        ]);
    }
}
