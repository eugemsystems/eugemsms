<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Items;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Stores\Domain\Actions\CreateInventoryItemAction;
use Modules\Stores\Domain\DataObjects\CreateInventoryItemData;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\ItemCategory;

/**
 * `Stores\Items\Index` (Book H1 FIN-09 §7, `inventory.item.view`/
 * `.manage`). Also stands in for the spec's separate "Item editor"
 * screen — no `UpdateInventoryItemAction` exists in the domain layer,
 * the same create-only precedent `Stores\Stores\Index` documents.
 */
#[Title('Inventory items')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $baseUnit = 'each';

    public ?int $categoryId = null;

    public ?string $purchaseUnit = null;

    public float $purchaseConversion = 1.0;

    public ?string $issueUnit = null;

    public float $issueConversion = 1.0;

    public bool $isPerishable = false;

    public bool $requiresBatchTracking = false;

    public ?int $shelfLifeDays = null;

    public bool $isHighRisk = false;

    public bool $isSaleable = false;

    public ?int $salePriceMinor = null;

    public bool $isCapitalisable = false;

    public ?int $capitalisationThresholdMinor = null;

    public ?int $assetCategoryId = null;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('inventory.item.view');
    }

    public function create(): void
    {
        $this->authorizePermission('inventory.item.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'baseUnit' => ['required', 'string', 'max:20'],
        ]);

        try {
            app(CreateInventoryItemAction::class)->execute(new CreateInventoryItemData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                baseUnit: $this->baseUnit,
                categoryId: $this->categoryId,
                purchaseUnit: $this->purchaseUnit,
                purchaseConversion: $this->purchaseConversion,
                issueUnit: $this->issueUnit,
                issueConversion: $this->issueConversion,
                isPerishable: $this->isPerishable,
                requiresBatchTracking: $this->requiresBatchTracking,
                shelfLifeDays: $this->shelfLifeDays,
                isHighRisk: $this->isHighRisk,
                isSaleable: $this->isSaleable,
                salePriceMinor: $this->salePriceMinor,
                saleCurrency: $this->isSaleable ? 'USD' : null,
                isCapitalisable: $this->isCapitalisable,
                capitalisationThresholdMinor: $this->capitalisationThresholdMinor,
                assetCategoryId: $this->assetCategoryId,
                createdByUserId: (int) auth()->id(),
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset([
            'code', 'name', 'purchaseUnit', 'issueUnit', 'shelfLifeDays', 'isPerishable',
            'requiresBatchTracking', 'isHighRisk', 'isSaleable', 'salePriceMinor',
            'isCapitalisable', 'capitalisationThresholdMinor', 'assetCategoryId',
        ]);
        $this->toast(__('Item created.'));
    }

    public function render(): View
    {
        $items = InventoryItem::where('school_id', $this->school->id)
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('stores::items.index', [
            'items' => $items,
            'categories' => ItemCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'assetCategories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
