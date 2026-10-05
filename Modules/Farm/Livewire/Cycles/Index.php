<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Cycles;

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
use Modules\Farm\Domain\Actions\AllocateLabourCostAction;
use Modules\Farm\Domain\Actions\ApportionOverheadAction;
use Modules\Farm\Domain\Actions\FailCropCycleAction;
use Modules\Farm\Domain\Actions\PlanCropCycleAction;
use Modules\Farm\Domain\Actions\RecordCropInputAction;
use Modules\Farm\Domain\DataObjects\FailCropCycleData;
use Modules\Farm\Domain\DataObjects\PlanCropCycleData;
use Modules\Farm\Domain\DataObjects\RecordCropInputData;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\Account;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * `Cycles\Index` (Book H2 OPS-03 §5/BR-OPS-03-002/003/004/007,
 * `farm.crop.manage`). Folds the spec's separate "Input recording"
 * screen (`farm.record`) into this one's own per-cycle action bar —
 * inputs, labour and overhead all post against the selected cycle
 * directly, the same lifecycle fold `Maintenance\WorkOrders\Index`
 * uses for parts/labour/contractor cost. `cost_per_kg_minor` — the
 * internal transfer price — only exists once a harvest is recorded
 * (`Harvest\Index`), not here.
 */
#[Title('Crop cycles')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $productionUnitId = null;

    public ?int $fieldId = null;

    public string $cycleReference = '';

    public string $crop = '';

    public ?string $variety = null;

    public string $season = 'summer';

    public string $areaPlantedHectares = '';

    public ?int $selectedCycleId = null;

    public ?int $inputStoreId = null;

    public ?int $inputItemId = null;

    public string $inputType = 'fertiliser';

    public string $inputDescription = '';

    public string $inputQuantity = '';

    public string $inputUnit = 'kg';

    public string $labourDays = '';

    public ?int $overheadPoolMinor = null;

    public ?float $overheadTotalHectares = null;

    public string $failureReason = '';

    public ?int $cropFailureExpenseAccountId = null;

    public ?int $originalExpenseAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('farm.crop.manage');
    }

    public function plan(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'fieldId' => ['required', 'integer'],
            'cycleReference' => ['required', 'string', 'max:40'],
            'crop' => ['required', 'string', 'max:120'],
            'areaPlantedHectares' => ['required', 'numeric', 'gt:0'],
        ]);

        app(PlanCropCycleAction::class)->execute(new PlanCropCycleData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            productionUnitId: (int) $this->productionUnitId,
            fieldId: (int) $this->fieldId,
            cycleReference: $this->cycleReference,
            crop: $this->crop,
            season: $this->season,
            areaPlantedHectares: (float) $this->areaPlantedHectares,
            currency: $this->school->base_currency,
            variety: $this->variety,
        ));

        $this->reset(['cycleReference', 'crop', 'variety', 'areaPlantedHectares']);
        $this->toast(__('Crop cycle planned.'));
    }

    public function select(int $cycleId): void
    {
        $this->selectedCycleId = $cycleId;
    }

    public function recordInput(int $cycleId): void
    {
        $this->authorizePermission('farm.record');

        $this->validate([
            'inputStoreId' => ['required', 'integer'],
            'inputItemId' => ['required', 'integer'],
            'inputDescription' => ['required', 'string'],
            'inputQuantity' => ['required', 'numeric', 'gt:0'],
        ]);

        app(RecordCropInputAction::class)->execute(new RecordCropInputData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            cropCycleId: $cycleId,
            storeId: (int) $this->inputStoreId,
            itemId: (int) $this->inputItemId,
            inputType: $this->inputType,
            description: $this->inputDescription,
            quantity: (float) $this->inputQuantity,
            unit: $this->inputUnit,
            appliedOn: Carbon::now(),
            appliedByUserId: (int) auth()->id(),
        ));

        $this->reset(['inputDescription', 'inputQuantity']);
        $this->toast(__('Input recorded.'));
    }

    public function allocateLabour(int $cycleId): void
    {
        $this->validate(['labourDays' => ['required', 'numeric', 'gt:0']]);

        app(AllocateLabourCostAction::class)->execute($cycleId, (float) $this->labourDays);
        $this->labourDays = '';
        $this->toast(__('Labour cost allocated.'));
    }

    public function apportionOverhead(int $cycleId): void
    {
        $this->validate(['overheadPoolMinor' => ['required', 'integer', 'gt:0']]);

        app(ApportionOverheadAction::class)->execute($cycleId, (int) $this->overheadPoolMinor, $this->overheadTotalHectares);
        $this->reset(['overheadPoolMinor', 'overheadTotalHectares']);
        $this->toast(__('Overhead apportioned.'));
    }

    public function fail(int $cycleId): void
    {
        $this->validate([
            'failureReason' => ['required', 'string'],
            'cropFailureExpenseAccountId' => ['required', 'integer'],
            'originalExpenseAccountId' => ['required', 'integer'],
        ]);

        try {
            app(FailCropCycleAction::class)->execute($cycleId, new FailCropCycleData(
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                failureReason: $this->failureReason,
                cropFailureExpenseAccountId: (int) $this->cropFailureExpenseAccountId,
                originalExpenseAccountId: (int) $this->originalExpenseAccountId,
                performedByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['failureReason', 'cropFailureExpenseAccountId', 'originalExpenseAccountId']);
        $this->toast(__('Crop cycle failed — cost written off to its own expense line.'));
    }

    public function render(): View
    {
        $selected = $this->selectedCycleId !== null
            ? CropCycle::with('inputs')->where('school_id', $this->school->id)->find($this->selectedCycleId)
            : null;

        return view('farm::cycles.index', [
            'cycles' => CropCycle::with('productionUnit', 'field')->where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'selected' => $selected,
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
            'fields' => $this->productionUnitId !== null ? FarmField::where('production_unit_id', $this->productionUnitId)->get() : collect(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
