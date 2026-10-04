<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Receipts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `Stores\Receipts\Create` (Book H1 FIN-09 §7, `inventory.receipt.create`).
 * Direct/opening-stock receipt into a `FIN-09` lot — `FIN-08`'s own
 * goods-received-note screen (`Procurement\Receipts\Create`) is the
 * path for a receipt against a purchase order; this one covers the
 * "no PO" cases BR-FIN-09-026 names (opening balance import, a direct
 * manual receipt). Posts a real journal in the same transaction as the
 * lot (BR-FIN-09-003) — the contra account is picked from the full
 * postable chart, the same "no system key for this" pattern
 * `Finance\Receipts\Capture` already established for income/refundable-
 * deposit accounts (see `.ai/rules/people.md`'s PPL-02 note).
 */
#[Title('Receive stock')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public string $quantity = '';

    public string $unitCostMinor = '';

    public string $currency = 'USD';

    public string $receivedOn;

    public ?int $contraAccountId = null;

    public string $sourceType = 'opening';

    public ?string $batchNumber = null;

    public ?string $expiryDate = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.receipt.create');
        $this->receivedOn = now()->toDateString();
    }

    public function receive(): void
    {
        $this->validate([
            'storeId' => ['required', 'integer'],
            'itemId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unitCostMinor' => ['required', 'integer', 'min:0'],
            'receivedOn' => ['required', 'date'],
            'contraAccountId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(ReceiveStockAction::class)->execute(new ReceiveStockData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                storeId: (int) $this->storeId,
                itemId: (int) $this->itemId,
                quantity: (float) $this->quantity,
                unitCostMinor: (int) $this->unitCostMinor,
                currency: $this->currency,
                receivedOn: Carbon::parse($this->receivedOn),
                performedByUserId: (int) auth()->id(),
                contraAccountId: (int) $this->contraAccountId,
                sourceType: $this->sourceType,
                batchNumber: $this->batchNumber,
                expiryDate: $this->expiryDate !== null ? Carbon::parse($this->expiryDate) : null,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['quantity', 'unitCostMinor', 'batchNumber', 'expiryDate']);
        $this->toast(__('Stock received.'));
    }

    public function render(): View
    {
        return view('stores::receipts.create', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
