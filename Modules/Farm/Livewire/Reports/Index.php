<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\ComputeProfitabilityAction;
use Modules\Farm\Domain\Actions\ComputeSavingsReportAction;
use Modules\Farm\Models\ProductionUnit;

/**
 * `Reports\Index` (Book H2 OPS-03 §5 ⭐/BR-OPS-03-017,
 * `farm.report.view`). Folds the spec's two separate "Profitability"
 * and "Savings report" screens into one tabbed read, the same fold
 * `Maintenance\Reports\Index`/`Procurement\Reports\Index` already use
 * for their own multi-screen spec sections.
 */
#[Title('Farm reports')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $tab = 'profitability';

    public ?int $productionUnitId = null;

    public string $periodStart = '';

    public string $periodEnd = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('farm.report.view');
        $this->periodStart = now()->startOfYear()->toDateString();
        $this->periodEnd = now()->endOfYear()->toDateString();
    }

    public function render(): View
    {
        $profitability = $this->productionUnitId !== null
            ? app(ComputeProfitabilityAction::class)->execute(
                $this->productionUnitId,
                (int) SessionContext::termId(),
                Carbon::parse($this->periodStart),
                Carbon::parse($this->periodEnd),
            )
            : null;

        $savings = app(ComputeSavingsReportAction::class)->execute(
            $this->school->id,
            Carbon::parse($this->periodStart),
            Carbon::parse($this->periodEnd),
        );

        return view('farm::reports.index', [
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
            'profitability' => $profitability,
            'savings' => $savings,
        ]);
    }
}
