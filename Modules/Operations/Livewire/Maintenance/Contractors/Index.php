<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance\Contractors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Operations\Models\WorkOrder;
use Modules\Stores\Domain\Actions\SetSupplierContractorStatusAction;
use Modules\Stores\Models\Supplier;

/**
 * `Maintenance\Contractors` (Book H2 OPS-02 §6, `maintenance.manage`). Nothing anywhere flagged a
 * supplier as a contractor before this pass — `supplier_type` is a legal-entity-type field, and a
 * work order's own `assigned_team`/`contractor_supplier_id` columns name which specific supplier
 * did one job, never which suppliers are generally available for one. This screen is the missing
 * middle: flag/unflag any `Modules\Stores` supplier as a contractor, then see each flagged
 * contractor's real cost and SLA history, read straight off `work_orders` the same way
 * `Maintenance\Reports\Index` already reads it (grouped in PHP over an eager-loaded collection,
 * never a raw join across `BelongsToSchool` models).
 */
#[Title('Contractor management')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('maintenance.manage');
    }

    public function toggle(int $supplierId): void
    {
        $this->authorizePermission('maintenance.manage');

        $supplier = Supplier::where('school_id', $this->school->id)->findOrFail($supplierId);

        app(SetSupplierContractorStatusAction::class)->execute($supplierId, ! $supplier->is_contractor);

        $this->toast($supplier->is_contractor ? __('Removed as a contractor.') : __('Flagged as a contractor.'));
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function searchResults(): Collection
    {
        if ($this->search === '') {
            return collect();
        }

        return Supplier::where('school_id', $this->school->id)
            ->where('is_contractor', false)
            ->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public function render(): View
    {
        $contractors = Supplier::where('school_id', $this->school->id)->where('is_contractor', true)->orderBy('name')->get();

        $workOrders = WorkOrder::where('school_id', $this->school->id)
            ->whereIn('contractor_supplier_id', $contractors->pluck('id'))
            ->get();

        $history = $workOrders->groupBy('contractor_supplier_id')->map(function (Collection $orders): array {
            $completed = $orders->whereNotNull('sla_met');

            return [
                'work_orders' => $orders->count(),
                'total_cost_minor' => (int) $orders->sum('contractor_cost_minor'),
                'sla_met' => $completed->where('sla_met', true)->count(),
                'sla_total' => $completed->count(),
            ];
        });

        return view('operations::maintenance.contractors.index', [
            'contractors' => $contractors,
            'history' => $history,
            'searchResults' => $this->searchResults(),
        ]);
    }
}
