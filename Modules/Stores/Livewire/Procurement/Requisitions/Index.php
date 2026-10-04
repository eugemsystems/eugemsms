<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Procurement\Requisitions;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
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
use Modules\Stores\Domain\Actions\ApprovePurchaseRequisitionAction;
use Modules\Stores\Domain\Actions\RequestPurchaseRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestPurchaseRequisitionData;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\PurchaseRequisition;

/**
 * `Procurement\Requisitions\Index` (Book H1 FIN-08 §7, `procurement.requisition.create`/
 * `.approve`, FIN-11 BR-FIN-11-009/AC-FIN-11-004). The budget indicator
 * shown per row is read straight off `budget_check_result`/
 * `budget_available_minor` — real values `RequestPurchaseRequisitionAction`
 * itself recorded at submission via `FIN-11`'s own
 * `CheckBudgetAvailabilityAction`, never recomputed here. Exceeding
 * budget is shown, never blocked (BR-FIN-11-009) — approval still
 * proceeds through the same single-gate `ApprovePurchaseRequisitionAction`
 * every other domain module in this codebase uses in place of a real
 * `CORE-07` chain.
 */
#[Title('Purchase requisitions')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $departmentId = null;

    public ?int $costCentreId = null;

    public ?int $budgetLineId = null;

    public string $justification = '';

    public string $urgency = 'normal';

    /** @var array<int, array{item_id: string, description: string, quantity: string, unit: string, estimated_unit_minor: string}> */
    public array $lines = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('procurement.requisition.create');
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_id' => '', 'description' => '', 'quantity' => '', 'unit' => 'each', 'estimated_unit_minor' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function submit(): void
    {
        $this->validate([
            'departmentId' => ['required', 'integer'],
            'costCentreId' => ['required', 'integer'],
            'justification' => ['required', 'string'],
            'lines' => ['array', 'min:1'],
            'lines.*.description' => ['required', 'string'],
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
            app(RequestPurchaseRequisitionAction::class)->execute(new RequestPurchaseRequisitionData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                departmentId: (int) $this->departmentId,
                costCentreId: (int) $this->costCentreId,
                justification: $this->justification,
                requestedByUserId: (int) auth()->id(),
                currency: 'USD',
                lines: collect($this->lines)->map(fn (array $l): array => [
                    'itemId' => $l['item_id'] !== '' ? (int) $l['item_id'] : null,
                    'description' => $l['description'],
                    'quantity' => (float) $l['quantity'],
                    'unit' => $l['unit'],
                    'estimatedUnitMinor' => $l['estimated_unit_minor'] !== '' ? (int) $l['estimated_unit_minor'] : null,
                ])->all(),
                urgency: $this->urgency,
                budgetLineId: $this->budgetLineId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['justification', 'lines']);
        $this->addLine();
        $this->toast(__('Requisition submitted.'));
    }

    public function approve(int $requisitionId): void
    {
        $this->authorizePermission('procurement.requisition.approve');

        try {
            app(ApprovePurchaseRequisitionAction::class)->execute($requisitionId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Requisition approved.'));
    }

    public function render(): View
    {
        return view('stores::procurement.requisitions.index', [
            'requisitions' => PurchaseRequisition::where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'departments' => Department::where('school_id', $this->school->id)->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'budgetLines' => BudgetLine::where('school_id', $this->school->id)->orderBy('id')->limit(200)->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
        ]);
    }
}
