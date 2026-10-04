<?php

declare(strict_types=1);

namespace Modules\Stores\Livewire\Assets\Register;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Staff;
use Modules\Stores\Domain\Actions\ChangeAssetCustodianAction;
use Modules\Stores\Domain\Actions\ChangeAssetStatusAction;
use Modules\Stores\Domain\Actions\TransferAssetAction;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

/**
 * `Assets\Register\Show` (Book H1 FIN-10 §5, `assets.view`/`.manage`/
 * `.transfer`). Hosts status/condition change, custodian change, and
 * transfer all on one screen — folding the spec's separate "Transfer"
 * screen in here, the same lifecycle-action-bar shape used throughout
 * this book. Every change writes its own append-only `asset_movements`
 * row (BR-FIN-10-009), shown below, never edited.
 */
#[Title('Asset')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public FixedAsset $asset;

    public ?string $newStatus = null;

    public ?string $newCondition = null;

    public ?string $statusReason = null;

    public ?int $newCustodianStaffId = null;

    public ?int $newCostCentreId = null;

    public ?string $transferReason = null;

    public function mount(School $school, FixedAsset $asset): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('assets.view');
        $this->asset = $asset;
    }

    public function changeStatus(): void
    {
        $this->authorizePermission('assets.manage');

        $this->asset = app(ChangeAssetStatusAction::class)->execute(
            $this->asset->id,
            $this->newStatus,
            $this->newCondition,
            (int) auth()->id(),
            $this->statusReason,
        );

        $this->reset(['newStatus', 'newCondition', 'statusReason']);
        $this->toast(__('Status updated.'));
    }

    public function changeCustodian(): void
    {
        $this->authorizePermission('assets.manage');

        $this->asset = app(ChangeAssetCustodianAction::class)->execute(
            $this->asset->id,
            $this->newCustodianStaffId,
            (int) auth()->id(),
        );

        $this->toast(__('Custodian changed.'));
    }

    public function transfer(): void
    {
        $this->authorizePermission('assets.transfer');

        if ($this->newCostCentreId === null) {
            return;
        }

        $this->asset = app(TransferAssetAction::class)->execute(
            $this->asset->id,
            (int) $this->newCostCentreId,
            (int) auth()->id(),
            $this->transferReason,
        );

        $this->reset(['newCostCentreId', 'transferReason']);
        $this->toast(__('Asset transferred — future depreciation moves to the new cost centre.'));
    }

    public function render(): View
    {
        return view('stores::assets.register.show', [
            'movements' => AssetMovement::where('asset_id', $this->asset->id)->orderByDesc('occurred_at')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('id')->limit(300)->get(),
        ]);
    }
}
