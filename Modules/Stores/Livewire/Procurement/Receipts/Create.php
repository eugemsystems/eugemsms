<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Receipts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\RecordGoodsReceivedNoteAction;
use Modules\Stores\Domain\DataObjects\RecordGoodsReceivedNoteData;
use Modules\Stores\Models\PurchaseOrder;

/**
 * `Procurement\Receipts\Create` (Book H1 FIN-08 §7 ⭐⭐, `procurement.grn.create`,
 * AC-FIN-08-006). One journal per GRN with `FIN-09` stock lots created
 * in the same transaction (BR-FIN-08-012) — this screen only collects
 * what was actually delivered/accepted/rejected per PO line and the
 * GRN accrual account; `RecordGoodsReceivedNoteAction` does everything
 * else, including refusing a line that exceeds what's still
 * outstanding on the order.
 */
#[Title('Goods receipt')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $purchaseOrderId = null;

    public ?int $grnAccrualAccountId = null;

    public string $receivedOn;

    public ?string $deliveryNoteRef = null;

    /** @var array<int, array{quantity_delivered: string, quantity_accepted: string, quantity_rejected: string, rejection_reason: string, batch_number: string, expiry_date: string, unit_cost_minor: string}> poLineId => line */
    public array $lines = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('procurement.grn.create');
        $this->receivedOn = now()->toDateString();
    }

    public function updatedPurchaseOrderId(): void
    {
        $order = PurchaseOrder::with('lines')->find($this->purchaseOrderId);
        $this->lines = [];

        foreach ($order === null ? [] : $order->lines as $line) {
            $this->lines[$line->id] = [
                'quantity_delivered' => '', 'quantity_accepted' => '', 'quantity_rejected' => '0',
                'rejection_reason' => '', 'batch_number' => '', 'expiry_date' => '',
                'unit_cost_minor' => (string) $line->unit_price_minor,
            ];
        }
    }

    public function receive(): void
    {
        $this->validate([
            'purchaseOrderId' => ['required', 'integer'],
            'grnAccrualAccountId' => ['required', 'integer'],
            'receivedOn' => ['required', 'date'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        $lines = [];

        foreach ($this->lines as $poLineId => $line) {
            if ($line['quantity_delivered'] === '') {
                continue;
            }

            $lines[] = [
                'poLineId' => (int) $poLineId,
                'quantityDelivered' => (float) $line['quantity_delivered'],
                'quantityAccepted' => (float) $line['quantity_accepted'],
                'quantityRejected' => (float) ($line['quantity_rejected'] ?: 0),
                'rejectionReason' => $line['rejection_reason'] !== '' ? $line['rejection_reason'] : null,
                'batchNumber' => $line['batch_number'] !== '' ? $line['batch_number'] : null,
                'expiryDate' => $line['expiry_date'] !== '' ? Carbon::parse($line['expiry_date']) : null,
                'unitCostMinor' => (int) $line['unit_cost_minor'],
            ];
        }

        if ($lines === []) {
            $this->toast(__('Enter at least one delivered quantity.'), 'danger');

            return;
        }

        try {
            $grn = app(RecordGoodsReceivedNoteAction::class)->execute(new RecordGoodsReceivedNoteData(
                schoolId: $this->school->id,
                termId: $termId,
                purchaseOrderId: (int) $this->purchaseOrderId,
                receivedOn: Carbon::parse($this->receivedOn),
                receivedByUserId: (int) auth()->id(),
                grnAccrualAccountId: (int) $this->grnAccrualAccountId,
                lines: $lines,
                deliveryNoteRef: $this->deliveryNoteRef,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', $e->validator->errors()->all()), 'danger');

            return;
        }

        $this->reset(['purchaseOrderId', 'lines', 'deliveryNoteRef']);
        $this->toast(__('Goods received — :n.', ['n' => $grn->grn_number]));
    }

    public function render(): View
    {
        return view('stores::procurement.receipts.create', [
            'orders' => PurchaseOrder::with('lines')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['approved', 'sent', 'acknowledged', 'partially_received'])
                ->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
