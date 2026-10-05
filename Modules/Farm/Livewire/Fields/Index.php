<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Fields;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\CreateFarmFieldAction;
use Modules\Farm\Domain\DataObjects\CreateFarmFieldData;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\ProductionUnit;

/**
 * `Fields\Index` (Book H2 OPS-03 §5/BR-OPS-03-018, `farm.manage`).
 */
#[Title('Fields')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $productionUnitId = null;

    public string $code = '';

    public string $name = '';

    public string $areaHectares = '';

    public ?string $soilType = null;

    public bool $isIrrigated = false;

    public ?string $irrigationType = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('farm.manage');
    }

    public function create(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'areaHectares' => ['required', 'numeric', 'gt:0'],
        ]);

        app(CreateFarmFieldAction::class)->execute(new CreateFarmFieldData(
            schoolId: $this->school->id,
            productionUnitId: (int) $this->productionUnitId,
            code: $this->code,
            name: $this->name,
            areaHectares: (float) $this->areaHectares,
            soilType: $this->soilType,
            isIrrigated: $this->isIrrigated,
            irrigationType: $this->isIrrigated ? $this->irrigationType : null,
        ));

        $this->reset(['code', 'name', 'areaHectares', 'soilType', 'isIrrigated', 'irrigationType']);
        $this->toast(__('Field created.'));
    }

    public function render(): View
    {
        return view('farm::fields.index', [
            'fields' => FarmField::with('productionUnit')->where('school_id', $this->school->id)->orderBy('code')->get(),
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
