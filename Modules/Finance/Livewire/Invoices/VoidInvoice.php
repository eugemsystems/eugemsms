<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Invoices;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\VoidInvoiceAction;
use Modules\Finance\Domain\DataObjects\VoidInvoiceData;
use Modules\Finance\Models\Invoice;

/**
 * `Finance\Invoices\Void` (Book B FIN-03 §5/BR-FIN-03-004/005,
 * `finance.invoice.void` ⚠) — named `VoidInvoice` here, not `Void`:
 * `void` is a reserved word in PHP and cannot name a class (`Cannot use
 * "Void" as a class name as it is reserved`), the same category of
 * PHP-imposed naming workaround `Modules\Finance\Livewire\Fees\AdHocCharge`
 * needed for a different reason. Refused server-side (not merely
 * hidden) the moment any payment has been allocated (AC-FIN-03-002) —
 * `VoidInvoiceAction` itself is the enforcement point, this screen
 * just surfaces whatever it says.
 */
#[Title('Void invoice')]
#[Layout('layouts.app')]
final class VoidInvoice extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Invoice $invoice;

    public string $reason = '';

    public function mount(School $school, Invoice $invoice): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.invoice.void');
        $this->invoice = $invoice;
    }

    public function save(): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        try {
            app(VoidInvoiceAction::class)->execute(new VoidInvoiceData(
                invoiceId: $this->invoice->id,
                reason: $this->reason,
                voidedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.invoices.show', ['school' => $this->school, 'invoice' => $this->invoice], navigate: true);
    }

    public function render(): View
    {
        return view('finance::invoices.void');
    }
}
