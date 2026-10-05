<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Receipts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Domain\Actions\RaiseFiscalCreditNoteAction;
use Modules\Fiscal\Domain\Actions\SubmitFiscalReceiptAction;
use Modules\Fiscal\Domain\DataObjects\RaiseFiscalCreditNoteData;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * `Fiscal\Receipts\Retry` (Book H3 FIN-13 §6/§7 ⭐, `fiscal.retry`).
 * Failed and rejected receipts, with their full retained error
 * (BR-FIN-13-011 — never silently discarded), retried one at a time
 * through the real `SubmitFiscalReceiptAction`. Also hosts the manual
 * "raise credit note" control for an accepted receipt whose source
 * was voided outside a module that already wires this automatically
 * (`Modules\Wallet`'s `VoidWalletSaleAction` does; a farm sale or
 * facility hire void does not yet) — the correction theme this screen
 * already carries, rather than a new permission or route just for it.
 */
#[Title('Fiscal retry workbench')]
#[Layout('layouts.app')]
final class Retry extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $creditNoteReceiptId = null;

    public string $creditReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.retry');
    }

    public function retry(int $fiscalReceiptId): void
    {
        app(SubmitFiscalReceiptAction::class)->execute($fiscalReceiptId);
        $this->toast(__('Retry attempted.'));
    }

    public function startCreditNote(int $fiscalReceiptId): void
    {
        $this->creditNoteReceiptId = $fiscalReceiptId;
        $this->creditReason = '';
    }

    public function raiseCreditNote(): void
    {
        $this->validate(['creditReason' => ['required', 'string', 'min:5', 'max:255']]);

        try {
            app(RaiseFiscalCreditNoteAction::class)->execute(new RaiseFiscalCreditNoteData(
                originalFiscalReceiptId: (int) $this->creditNoteReceiptId,
                creditReason: $this->creditReason,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->creditNoteReceiptId = null;
        $this->toast(__('Credit note raised.'));
    }

    public function render(): View
    {
        return view('fiscal::receipts.retry', [
            'failedReceipts' => FiscalReceipt::where('school_id', $this->school->id)->whereIn('status', ['failed', 'rejected', 'offline_queued'])->orderBy('global_counter')->get(),
            'acceptedReceipts' => FiscalReceipt::where('school_id', $this->school->id)->where('status', 'accepted')->whereNull('credited_receipt_id')->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}
