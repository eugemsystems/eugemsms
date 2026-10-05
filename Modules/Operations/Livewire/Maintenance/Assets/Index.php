<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance\Assets;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Domain\Actions\CreateMaintenanceAssetAction;
use Modules\Operations\Domain\DataObjects\CreateMaintenanceAssetData;
use Modules\Operations\Models\MaintenanceAsset;

/**
 * `Maintenance\Assets\Index` (Book H2 OPS-02 §7, `maintenance.manage`/
 * `.view`). Also stands in for the spec's separate "Asset maintenance
 * history" screen — selecting an asset shows its own fault reports and
 * work orders inline. Create-only: this pass's own
 * `CreateMaintenanceAssetAction` is a gap-fill (see its own docblock)
 * and no `UpdateMaintenanceAssetAction` exists either.
 */
#[Title('Maintenance assets')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $assetType = 'building';

    public ?int $costCentreId = null;

    public ?string $location = null;

    public ?string $building = null;

    public string $criticality = 'normal';

    public ?int $serviceIntervalDays = null;

    public ?int $selectedAssetId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('maintenance.view');
    }

    public function create(): void
    {
        $this->authorizePermission('maintenance.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'assetType' => ['required', 'string'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateMaintenanceAssetAction::class)->execute(new CreateMaintenanceAssetData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            assetType: $this->assetType,
            costCentreId: (int) $this->costCentreId,
            location: $this->location,
            building: $this->building,
            criticality: $this->criticality,
            serviceIntervalDays: $this->serviceIntervalDays,
        ));

        $this->reset(['code', 'name', 'location', 'building', 'serviceIntervalDays']);
        $this->toast(__('Maintenance asset created.'));
    }

    public function selectAsset(int $assetId): void
    {
        $this->selectedAssetId = $assetId;
    }

    public function render(): View
    {
        $selected = $this->selectedAssetId !== null
            ? MaintenanceAsset::with(['faultReports', 'workOrders'])->where('school_id', $this->school->id)->find($this->selectedAssetId)
            : null;

        return view('operations::maintenance.assets.index', [
            'assets' => MaintenanceAsset::where('school_id', $this->school->id)->orderBy('name')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'selected' => $selected,
        ]);
    }
}
