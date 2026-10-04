<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Transfers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
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
use Modules\Stores\Domain\Actions\DispatchStockTransferAction;
use Modules\Stores\Domain\Actions\ReceiveStockTransferAction;
use Modules\Stores\Domain\DataObjects\DispatchStockTransferData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockTransfer;
use Modules\Stores\Models\Store;

/**
 * `Stores\Transfers\Index` (Book H1 FIN-09 §7, `inventory.transfer.manage`).
 * Dispatch, receive, and discrepancy all on one screen — the same
 * "lifecycle action bar" shape as `Requisitions\Issue`. A discrepancy
 * is never silently absorbed: `ReceiveStockTransferAction` itself sets
 * `status = discrepancy` and this screen shows it in red, with no
 * "force complete" control anywhere (BR-FIN-09-019).
 */
#[Title('Stock transfers')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $fromStoreId = null;

    public ?int $toStoreId = null;

    public string $reason = '';

    /** @var array<int, array{item_id: string, quantity: string}> */
    public array $lines = [];

    /** @var array<int, array<int, string>> transferId => [lineId => receivedQty] */
    public array $received = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.transfer.manage');
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => '', 'quantity' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function dispatchTransfer(): void
    {
        $this->validate([
            'fromStoreId' => ['required', 'integer'],
            'toStoreId' => ['required', 'integer', 'different:fromStoreId'],
            'reason' => ['required', 'string', 'max:255'],
            'lines' => ['array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(DispatchStockTransferAction::class)->execute(new DispatchStockTransferData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                fromStoreId: (int) $this->fromStoreId,
                toStoreId: (int) $this->toStoreId,
                reason: $this->reason,
                items: collect($this->lines)->map(fn (array $l): array => ['itemId' => (int) $l['item_id'], 'quantity' => (float) $l['quantity']])->all(),
                dispatchedByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['reason', 'lines']);
        $this->addLine();
        $this->toast(__('Transfer dispatched.'));
    }

    public function receive(int $transferId): void
    {
        $quantities = array_map(static fn (string $q): float => (float) $q, array_filter($this->received[$transferId] ?? []));

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(ReceiveStockTransferAction::class)->execute($transferId, $quantities, (int) auth()->id(), $yearId, $termId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Transfer received.'));
    }

    public function render(): View
    {
        return view('stores::transfers.index', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
            'inTransit' => StockTransfer::with('lines.item', 'fromStore', 'toStore')
                ->where('school_id', $this->school->id)
                ->where('status', 'in_transit')
                ->get(),
            'completed' => StockTransfer::with('fromStore', 'toStore')
                ->where('school_id', $this->school->id)
                ->whereIn('status', ['received', 'discrepancy'])
                ->orderByDesc('id')
                ->limit(25)
                ->get(),
        ]);
    }
}
