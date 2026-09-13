<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Invoices;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ReceiptAllocation;

/**
 * `Finance\Invoices\Show` (Book B FIN-03 §5, `finance.invoice.view`) —
 * lines, calculation notes, allocations, and the journal link.
 */
#[Title('Invoice')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Invoice $invoice;

    public function mount(School $school, Invoice $invoice): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.invoice.view');
        $this->invoice = $invoice->load('lines.component', 'student', 'journal', 'replacedBy');
    }

    public function render(): View
    {
        return view('finance::invoices.show', [
            'allocations' => ReceiptAllocation::where('invoice_id', $this->invoice->id)->with('receipt')->get(),
        ]);
    }
}
