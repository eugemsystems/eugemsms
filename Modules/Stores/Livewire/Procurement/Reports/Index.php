<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\ComputeSupplierAgingAction;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * `Procurement\Reports\Index` (Book H1 FIN-08 §7, `procurement.report.view`).
 * Folds the spec's four separate report screens — Supplier aging,
 * Unclaimable VAT 🇿🇼, Withholding schedule 🇿🇼, Spend analytics — into
 * one tabbed screen, the same fold `Stores\Reports\Valuation`/
 * `Consumption` keep as two but this report set collapses further
 * since all four read the same `supplier_invoices` table from
 * different angles.
 */
#[Title('Procurement reports')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $tab = 'aging';

    public ?int $agingSupplierId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('procurement.report.view');
    }

    public function render(): View
    {
        $aging = $this->agingSupplierId !== null
            ? app(ComputeSupplierAgingAction::class)->execute($this->agingSupplierId)
            : collect();

        $unclaimableVat = SupplierInvoice::with('supplier')
            ->where('school_id', $this->school->id)
            ->where('is_fiscal_invoice', false)
            ->where('input_vat_claimable', false)
            ->whereHas('supplier', fn ($q) => $q->where('is_vat_registered', true))
            ->orderByDesc('invoice_date')
            ->limit(100)
            ->get();

        $withholding = SupplierInvoice::with('supplier')
            ->where('school_id', $this->school->id)
            ->where('withholding_applied', true)
            ->orderByDesc('invoice_date')
            ->limit(100)
            ->get();

        // A join against `suppliers` would collide on the unqualified
        // `school_id` column both tables' own `BelongsToSchool` global
        // scope injects — grouping in PHP over the already-scoped
        // collection avoids the ambiguity entirely.
        $spend = SupplierInvoice::with('supplier')
            ->where('school_id', $this->school->id)
            ->get()
            ->groupBy(fn (SupplierInvoice $invoice): string => $invoice->supplier->name)
            ->map(fn ($invoices, string $name): object => (object) [
                'supplier_name' => $name,
                'total_minor' => (int) $invoices->sum('total_minor'),
                'invoice_count' => $invoices->count(),
            ])
            ->sortByDesc('total_minor')
            ->take(25)
            ->values();

        return view('stores::procurement.reports.index', [
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'aging' => $aging,
            'unclaimableVat' => $unclaimableVat,
            'withholding' => $withholding,
            'spend' => $spend,
        ]);
    }
}
