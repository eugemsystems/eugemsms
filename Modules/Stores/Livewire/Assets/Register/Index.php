<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Register;

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
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CapitalizeAssetAction;
use Modules\Stores\Domain\DataObjects\CapitalizeAssetData;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;

/**
 * `Assets\Register\Index` (Book H1 FIN-10 §5, `assets.view`/`.manage`).
 * Also stands in for the spec's separate "Create asset" screen —
 * `CapitalizeAssetAction` is the single entry point for every
 * capitalisation source (direct purchase, donation, and in future the
 * `FIN-08`/`FIN-09` automatic listeners this pass deliberately
 * defers, per `StoresServiceProvider`'s own docblock), so this screen
 * exercises it directly rather than inventing a second create path.
 */
#[Title('Asset register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $categoryId = null;

    public string $name = '';

    public string $acquisitionDate;

    public string $acquisitionCostMinor = '';

    public string $acquisitionSource = 'purchase';

    public ?int $costCentreId = null;

    public ?int $contraAccountId = null;

    public ?string $donorName = null;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('assets.view');
        $this->acquisitionDate = now()->toDateString();
    }

    public function capitalize(): void
    {
        $this->authorizePermission('assets.manage');

        $this->validate([
            'categoryId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:200'],
            'acquisitionDate' => ['required', 'date'],
            'acquisitionCostMinor' => ['required', 'integer', 'min:0'],
            'costCentreId' => ['required', 'integer'],
            'contraAccountId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(CapitalizeAssetAction::class)->execute(new CapitalizeAssetData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                categoryId: (int) $this->categoryId,
                name: $this->name,
                acquisitionDate: Carbon::parse($this->acquisitionDate),
                acquisitionCostMinor: (int) $this->acquisitionCostMinor,
                currency: 'USD',
                acquisitionSource: $this->acquisitionSource,
                costCentreId: (int) $this->costCentreId,
                contraAccountId: (int) $this->contraAccountId,
                performedByUserId: (int) auth()->id(),
                donorName: $this->donorName,
            ));
        } catch (ValidationException $e) {
            $this->setErrorBag($e->errors());

            return;
        }

        $this->reset(['name', 'acquisitionCostMinor', 'donorName']);
        $this->toast(__('Asset capitalised.'));
    }

    public function render(): View
    {
        return view('stores::assets.register.index', [
            'assets' => FixedAsset::where('school_id', $this->school->id)
                ->when($this->search !== '', fn ($q) => $q->where(fn ($q2) => $q2->where('name', 'like', "%{$this->search}%")->orWhere('asset_tag', 'like', "%{$this->search}%")))
                ->orderByDesc('id')
                ->limit(100)
                ->get(),
            'categories' => AssetCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
