<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Requisitions;

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
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `Stores\Requisitions\Create` (Book H1 FIN-09 §7, `inventory.requisition.create`).
 * Item search and cost-centre selection up front — live availability
 * display is deferred (no `checkAvailability`-backed read is wired
 * into this screen yet; the storekeeper sees shortfalls for real at
 * `Requisitions\Issue` instead, where `StockCostingEngine::consume()`
 * actually runs).
 */
#[Title('New requisition')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $storeId = null;

    public ?int $costCentreId = null;

    public ?int $requestingDepartmentId = null;

    public string $purpose = '';

    public ?string $requiredBy = null;

    /** @var array<int, array{item_id: string, quantity: string, unit: string}> */
    public array $lines = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('inventory.requisition.create');
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => '', 'quantity' => '', 'unit' => 'each'];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function submit(): void
    {
        $this->validate([
            'storeId' => ['required', 'integer'],
            'costCentreId' => ['required', 'integer'],
            'purpose' => ['required', 'string', 'max:255'],
            'lines' => ['array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(RequestStoreRequisitionAction::class)->execute(new RequestStoreRequisitionData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                storeId: (int) $this->storeId,
                costCentreId: (int) $this->costCentreId,
                purpose: $this->purpose,
                requestedByUserId: (int) auth()->id(),
                currency: 'USD',
                lines: collect($this->lines)->map(fn (array $l): array => [
                    'itemId' => (int) $l['item_id'],
                    'quantity' => (float) $l['quantity'],
                    'unit' => $l['unit'],
                ])->all(),
                requestingDepartmentId: $this->requestingDepartmentId,
                requiredBy: $this->requiredBy !== null ? Carbon::parse($this->requiredBy) : null,
                sourceType: 'manual',
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['purpose', 'requiredBy', 'lines']);
        $this->addLine();
        $this->toast(__('Requisition submitted.'));
    }

    public function render(): View
    {
        return view('stores::requisitions.create', [
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'departments' => Department::where('school_id', $this->school->id)->orderBy('name')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
        ]);
    }
}
