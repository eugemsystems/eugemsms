<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance\WorkOrders;

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
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Domain\Actions\ApproveWorkOrderAction;
use Modules\Operations\Domain\Actions\CompleteWorkOrderAction;
use Modules\Operations\Domain\Actions\CreateWorkOrderAction;
use Modules\Operations\Domain\Actions\IssuePartsToWorkOrderAction;
use Modules\Operations\Domain\Actions\RecordContractorCostAction;
use Modules\Operations\Domain\Actions\RecordWorkOrderLabourAction;
use Modules\Operations\Domain\Actions\VerifyWorkOrderAction;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\DataObjects\IssuePartsToWorkOrderData;
use Modules\Operations\Domain\DataObjects\RecordWorkOrderLabourData;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\WorkOrder;
use Modules\People\Models\Staff;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * `Maintenance\WorkOrders\Index` (Book H2 OPS-02 §7, `maintenance.view`/
 * `.manage`/`.execute`). Folds the spec's separate "Technician job
 * card" screen into this one's own per-order action bar — complete,
 * issue parts, record labour and record contractor cost all operate
 * on the selected work order directly, the same fold
 * `Procurement\Orders\Index` uses for its own lifecycle. Manual
 * creation here is for a standalone work order with no originating
 * fault report (triage's own `Triage::convert()` is the other entry
 * point into `CreateWorkOrderAction`).
 */
#[Title('Work orders')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $workType = 'corrective';

    public string $title = '';

    public string $description = '';

    public string $priority = 'normal';

    public string $assignedTeam = 'in_house';

    public ?int $costCentreId = null;

    public ?int $maintenanceAssetId = null;

    public ?int $contractorSupplierId = null;

    public ?int $selectedWorkOrderId = null;

    public string $completionNotes = '';

    public ?int $partsStoreId = null;

    public ?int $partsItemId = null;

    public string $partsQuantity = '';

    public string $partsUnit = 'each';

    public string $partsDescription = '';

    public ?int $labourStaffId = null;

    public string $labourHours = '';

    public ?int $contractorInvoiceId = null;

    public string $contractorAmountMinor = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('maintenance.view');
    }

    public function create(): void
    {
        $this->authorizePermission('maintenance.manage');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string'],
            'costCentreId' => ['required', 'integer'],
        ]);

        try {
            app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
                schoolId: $this->school->id,
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                workType: $this->workType,
                title: $this->title,
                description: $this->description,
                priority: $this->priority,
                assignedTeam: $this->assignedTeam,
                costCentreId: (int) $this->costCentreId,
                currency: $this->school->base_currency,
                raisedByUserId: (int) auth()->id(),
                maintenanceAssetId: $this->maintenanceAssetId,
                contractorSupplierId: $this->contractorSupplierId,
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['title', 'description', 'maintenanceAssetId', 'contractorSupplierId']);
        $this->toast(__('Work order created.'));
    }

    public function select(int $workOrderId): void
    {
        $this->selectedWorkOrderId = $workOrderId;
    }

    public function approve(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.manage');

        try {
            app(ApproveWorkOrderAction::class)->execute($workOrderId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Work order approved.'));
    }

    public function complete(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.execute');

        try {
            app(CompleteWorkOrderAction::class)->execute($workOrderId, $this->completionNotes, null, (int) auth()->id());
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->completionNotes = '';
        $this->toast(__('Work order completed.'));
    }

    public function verify(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.view');

        try {
            app(VerifyWorkOrderAction::class)->execute($workOrderId, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Work order verified.'));
    }

    public function issueParts(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.execute');

        $this->validate([
            'partsStoreId' => ['required', 'integer'],
            'partsItemId' => ['required', 'integer'],
            'partsQuantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            app(IssuePartsToWorkOrderAction::class)->execute(new IssuePartsToWorkOrderData(
                workOrderId: $workOrderId,
                storeId: (int) $this->partsStoreId,
                issuedByUserId: (int) auth()->id(),
                lines: [[
                    'itemId' => (int) $this->partsItemId,
                    'quantity' => (float) $this->partsQuantity,
                    'unit' => $this->partsUnit,
                    'description' => $this->partsDescription,
                ]],
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['partsStoreId', 'partsItemId', 'partsQuantity', 'partsDescription']);
        $this->toast(__('Parts issued — cost posted to the work order and the ledger.'));
    }

    public function recordLabour(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.execute');

        $this->validate([
            'labourStaffId' => ['required', 'integer'],
            'labourHours' => ['required', 'numeric', 'gt:0'],
        ]);

        app(RecordWorkOrderLabourAction::class)->execute(new RecordWorkOrderLabourData(
            workOrderId: $workOrderId,
            staffId: (int) $this->labourStaffId,
            workDate: Carbon::now(),
            hours: (float) $this->labourHours,
            recordedByUserId: (int) auth()->id(),
        ));

        $this->reset(['labourStaffId', 'labourHours']);
        $this->toast(__('Labour recorded.'));
    }

    public function recordContractorCost(int $workOrderId): void
    {
        $this->authorizePermission('maintenance.execute');

        $this->validate([
            'contractorInvoiceId' => ['required', 'integer'],
            'contractorAmountMinor' => ['required', 'integer', 'gt:0'],
        ]);

        try {
            app(RecordContractorCostAction::class)->execute($workOrderId, (int) $this->contractorInvoiceId, (int) $this->contractorAmountMinor, (int) auth()->id());
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['contractorInvoiceId', 'contractorAmountMinor']);
        $this->toast(__('Contractor cost recorded.'));
    }

    public function render(): View
    {
        $selected = $this->selectedWorkOrderId !== null
            ? WorkOrder::with(['parts', 'labour'])->where('school_id', $this->school->id)->find($this->selectedWorkOrderId)
            : null;

        return view('operations::maintenance.work-orders.index', [
            'workOrders' => WorkOrder::where('school_id', $this->school->id)->orderByDesc('id')->limit(100)->get(),
            'selected' => $selected,
            'assets' => MaintenanceAsset::where('school_id', $this->school->id)->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'suppliers' => Supplier::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->limit(100)->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(300)->get(),
            'invoices' => $selected?->contractor_supplier_id !== null
                ? SupplierInvoice::where('school_id', $this->school->id)->where('supplier_id', $selected->contractor_supplier_id)->get()
                : collect(),
        ]);
    }
}
