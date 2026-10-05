<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Units;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\CreateProductionUnitAction;
use Modules\Farm\Domain\DataObjects\CreateProductionUnitData;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Staff;
use Modules\Stores\Models\Store;

/**
 * `Units\Index` (Book H2 OPS-03 §5 ⭐/BR-OPS-03-001, `farm.manage`).
 * Each production unit is its own cost centre with its own P&L — the
 * cost centre picker here is exactly that boundary, not a convenience
 * field.
 */
#[Title('Production units')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $unitType = 'crop';

    public ?int $costCentreId = null;

    public ?int $managerStaffId = null;

    public ?int $storeId = null;

    public ?float $areaHectares = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('farm.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateProductionUnitAction::class)->execute(new CreateProductionUnitData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            unitType: $this->unitType,
            costCentreId: (int) $this->costCentreId,
            managerStaffId: $this->managerStaffId,
            storeId: $this->storeId,
            areaHectares: $this->areaHectares,
        ));

        $this->reset(['code', 'name', 'managerStaffId', 'storeId', 'areaHectares']);
        $this->toast(__('Production unit created.'));
    }

    public function render(): View
    {
        return view('farm::units.index', [
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->limit(100)->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
