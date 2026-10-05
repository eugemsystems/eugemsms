<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\KitchenTransfers;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\TransferToKitchenAction;
use Modules\Farm\Domain\DataObjects\TransferToKitchenData;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\ProductionOutput;
use Modules\Farm\Models\ProductionUnit;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `KitchenTransfers\Index` ⭐ (Book H2 OPS-03 §5 ⭐⭐/BR-OPS-03-008/009/012 ⭐,
 * `farm.transfer`). Named `KitchenTransfers`, not the spec's own bare
 * `Transfers` — a plain `Transfers\Index` class collides with
 * `Modules\Stores\Livewire\Transfers\Index` (`FIN-09`'s inter-store
 * transfer screen, shipped in Book H1): Livewire's component Finder
 * resolves a full-page component by its path RELATIVE to whichever
 * `Livewire::addLocation()` root matches, so two different modules
 * each registering their own `Transfers/Index.php` produce the same
 * short component name — the one registered later silently wins for
 * BOTH routes, so a school granted only `farm.transfer` got a 403
 * (checked against `inventory.transfer.manage` instead) until this
 * rename. See `.ai/rules/farm.md` for the full story and how to check
 * for this before naming any new module-root Livewire component.
 * The withdrawal check itself is enforced entirely inside
 * `TransferToKitchenAction` itself (BR-OPS-03-012) — this screen never
 * duplicates it, it only surfaces the `WithdrawalPeriodActiveException`
 * refusal, which always names the withdrawal period and its end date.
 * `outputId` is deliberately optional and only offered for the
 * selected unit's own milk/meat `ProductionOutput` rows — the Action
 * only runs the withdrawal check at all when a transfer is linked to
 * one (`TransferToKitchenAction::execute()`'s own `$data->outputId !== null`
 * guard), the real behaviour this field exists to reach, not a
 * decorative extra.
 */
#[Title('Kitchen transfers')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $productionUnitId = null;

    public ?int $fromStoreId = null;

    public ?int $toStoreId = null;

    public ?int $itemId = null;

    public ?int $outputId = null;

    public string $quantity = '';

    public string $unit = 'kg';

    public ?int $marketPriceMinor = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('farm.transfer');
    }

    public function transfer(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'fromStoreId' => ['required', 'integer'],
            'toStoreId' => ['required', 'integer'],
            'itemId' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            app(TransferToKitchenAction::class)->execute(new TransferToKitchenData(
                schoolId: $this->school->id,
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                productionUnitId: (int) $this->productionUnitId,
                fromStoreId: (int) $this->fromStoreId,
                toStoreId: (int) $this->toStoreId,
                itemId: (int) $this->itemId,
                quantity: (float) $this->quantity,
                unit: $this->unit,
                transferDate: Carbon::now(),
                dispatchedByUserId: (int) auth()->id(),
                outputId: $this->outputId,
                marketPriceMinor: $this->marketPriceMinor,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['quantity', 'outputId', 'marketPriceMinor']);
        $this->toast(__('Produce transferred to the kitchen at internal cost.'));
    }

    public function render(): View
    {
        return view('farm::kitchen-transfers.index', [
            'transfers' => InternalTransfer::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
            'outputs' => $this->productionUnitId !== null
                ? ProductionOutput::where('production_unit_id', $this->productionUnitId)->whereIn('output_type', ['milk', 'meat'])->orderByDesc('output_date')->limit(30)->get()
                : collect(),
        ]);
    }
}
