<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;

/**
 * `Maintenance\Reports\Index` (Book H2 OPS-02 §7/BR-OPS-02-013,
 * `maintenance.report.view`). Folds the spec's two separate "SLA
 * report" and "Cost analysis" screens into one tabbed read, the same
 * fold `Procurement\Reports\Index` uses for its own four report
 * screens — both tabs read real stored columns (`sla_met`,
 * `total_cost_minor`) grouped in PHP over an eager-loaded collection
 * rather than a raw SQL join, the same `BelongsToSchool`-join trap
 * `.ai/rules/stores.md` already documents for this codebase.
 */
#[Title('Maintenance reports')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $tab = 'sla';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('maintenance.report.view');
    }

    public function render(): View
    {
        $completed = WorkOrder::where('school_id', $this->school->id)
            ->whereNotNull('completed_at')
            ->get();

        $slaByTeam = $completed->whereNotNull('sla_met')->groupBy('assigned_team')->map(fn ($group) => [
            'total' => $group->count(),
            'met' => $group->where('sla_met', true)->count(),
        ]);

        $costByAsset = WorkOrder::where('school_id', $this->school->id)
            ->whereNotNull('maintenance_asset_id')
            ->with('maintenanceAsset')
            ->get()
            ->groupBy(function (WorkOrder $wo): string {
                $asset = $wo->maintenanceAsset;

                return $asset !== null ? $asset->name : 'Unassigned';
            })
            ->map(fn ($group): int => (int) $group->sum('total_cost_minor'));

        $costByCostCentre = WorkOrder::where('school_id', $this->school->id)
            ->with('costCentre')
            ->get()
            ->groupBy(function (WorkOrder $wo): string {
                $costCentre = $wo->costCentre;

                return $costCentre !== null ? $costCentre->name : 'Unassigned';
            })
            ->map(fn ($group): int => (int) $group->sum('total_cost_minor'));

        return view('operations::maintenance.reports.index', [
            'slaByTeam' => $slaByTeam,
            'costByAsset' => $costByAsset,
            'costByCostCentre' => $costByCostCentre,
        ]);
    }
}
