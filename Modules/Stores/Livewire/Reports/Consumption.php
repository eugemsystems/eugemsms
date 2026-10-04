<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\StockMovement;

/**
 * `Stores\Reports\Consumption` (Book H1 FIN-09 §7, `inventory.report.view`).
 * Issue movements summed per cost centre over the chosen window — the
 * same requesting-cost-centre attribution `IssueStockAction` writes
 * onto every movement (BR-FIN-09-006), read back here rather than
 * re-derived.
 */
#[Title('Consumption report')]
#[Layout('layouts.app')]
final class Consumption extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $from;

    public string $to;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.report.view');
        $this->from = now()->subDays(30)->toDateString();
        $this->to = now()->toDateString();
    }

    public function render(): View
    {
        $rows = StockMovement::query()
            ->where('school_id', $this->school->id)
            ->where('direction', 'out')
            ->where('movement_type', 'issue')
            ->whereBetween('occurred_at', [Carbon::parse($this->from)->startOfDay(), Carbon::parse($this->to)->endOfDay()])
            ->selectRaw('cost_centre_id, SUM(total_cost_minor) as total_minor, COUNT(*) as movement_count')
            ->groupBy('cost_centre_id')
            ->get();

        $costCentres = CostCentre::whereIn('id', $rows->pluck('cost_centre_id'))->get()->keyBy('id');

        return view('stores::reports.consumption', [
            'rows' => $rows,
            'costCentres' => $costCentres,
        ]);
    }
}
