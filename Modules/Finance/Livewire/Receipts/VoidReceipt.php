<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Receipts;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\VoidReceiptAction;
use Modules\Finance\Domain\DataObjects\VoidReceiptData;
use Modules\Finance\Models\Receipt;

/**
 * `Finance\Receipts\Void` (Book B FIN-04 §4/BR-FIN-04-020,
 * `finance.receipt.void` ⚠) — named `VoidReceipt`, not `Void`: `void`
 * is a PHP reserved word and cannot name a class, the same workaround
 * FIN-03's `Invoices\VoidInvoice` needed. `VoidReceiptAction` reverses
 * the journal and every allocation (recorded, not deleted) and
 * restores invoice balances; this screen only surfaces its result.
 */
#[Title('Void receipt')]
#[Layout('layouts.app')]
final class VoidReceipt extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Receipt $receipt;

    public string $reason = '';

    public function mount(School $school, Receipt $receipt): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.receipt.void');
        $this->receipt = $receipt->load(['tenders', 'allocations']);
    }

    public function save(): void
    {
        $this->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);

        try {
            app(VoidReceiptAction::class)->execute(new VoidReceiptData(
                receiptId: $this->receipt->id,
                reason: $this->reason,
                voidedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.receipts.show', ['school' => $this->school, 'receipt' => $this->receipt], navigate: true);
    }

    public function render(): View
    {
        return view('finance::receipts.void-receipt');
    }
}
