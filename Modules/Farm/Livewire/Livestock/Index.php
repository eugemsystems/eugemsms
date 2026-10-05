<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Livestock;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\CheckMortalityRateAction;
use Modules\Farm\Domain\Actions\CreateLivestockAction;
use Modules\Farm\Domain\DataObjects\CreateLivestockData;
use Modules\Farm\Models\Livestock as LivestockModel;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Models\AssetCategory;

/**
 * `Livestock\Index` (Book H2 OPS-03 §5/BR-OPS-03-010/011,
 * `farm.livestock.manage`). Capitalisation fields only appear for
 * `purpose = breeding` — `CreateLivestockAction` itself decides
 * whether the threshold and setting are actually met
 * (`shouldCapitalize()`), this form only offers the inputs it would
 * need to.
 */
#[Title('Livestock register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $productionUnitId = null;

    public string $species = 'cattle';

    public string $purpose = 'dairy';

    public bool $isHerdRecord = false;

    public int $headCount = 1;

    public ?string $tagNumber = null;

    public ?string $breed = null;

    public ?string $acquisitionCostMinor = null;

    public ?int $capitalizeCategoryId = null;

    public ?int $capitalizeCostCentreId = null;

    public ?int $capitalizeContraAccountId = null;

    public ?int $mortalityUnitId = null;

    public ?float $mortalityResult = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('farm.livestock.manage');
    }

    public function create(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'species' => ['required', 'string'],
            'purpose' => ['required', 'string'],
            'headCount' => ['required', 'integer', 'gt:0'],
        ]);

        app(CreateLivestockAction::class)->execute(new CreateLivestockData(
            schoolId: $this->school->id,
            productionUnitId: (int) $this->productionUnitId,
            species: $this->species,
            purpose: $this->purpose,
            isHerdRecord: $this->isHerdRecord,
            headCount: $this->headCount,
            tagNumber: $this->tagNumber,
            breed: $this->breed,
            acquiredOn: Carbon::now(),
            acquisitionType: 'purchased',
            acquisitionCostMinor: $this->acquisitionCostMinor !== null && $this->acquisitionCostMinor !== '' ? (int) $this->acquisitionCostMinor : null,
            currency: $this->school->base_currency,
            capitalizeCategoryId: $this->capitalizeCategoryId,
            capitalizeCostCentreId: $this->capitalizeCostCentreId,
            capitalizeContraAccountId: $this->capitalizeContraAccountId,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            performedByUserId: (int) auth()->id(),
        ));

        $this->reset(['tagNumber', 'breed', 'acquisitionCostMinor', 'capitalizeCategoryId', 'capitalizeCostCentreId', 'capitalizeContraAccountId']);
        $this->toast(__('Livestock recorded.'));
    }

    public function checkMortality(): void
    {
        if ($this->mortalityUnitId === null) {
            return;
        }

        $this->mortalityResult = app(CheckMortalityRateAction::class)->execute(
            $this->mortalityUnitId,
            now()->subDays(90),
            now(),
        );
    }

    public function render(): View
    {
        return view('farm::livestock.index', [
            'livestock' => LivestockModel::with('productionUnit')->where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
            'assetCategories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
