<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Generators;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Domain\Actions\CreateGeneratorAction;
use Modules\Utilities\Domain\DataObjects\CreateGeneratorData;
use Modules\Utilities\Models\Generator;

/**
 * `Generators\Index` (Book H2 OPS-04 §6, `utilities.generator.manage`).
 */
#[Title('Generators')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $capacityKva = '';

    public ?int $costCentreId = null;

    public string $fuelType = 'diesel';

    public ?string $tankCapacityLitres = null;

    public ?string $expectedLitresPerHour = null;

    public string $servesScope = 'whole_school';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.generator.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'capacityKva' => ['required', 'numeric', 'gt:0'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateGeneratorAction::class)->execute(new CreateGeneratorData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            capacityKva: (float) $this->capacityKva,
            costCentreId: (int) $this->costCentreId,
            fuelType: $this->fuelType,
            tankCapacityLitres: $this->tankCapacityLitres !== null && $this->tankCapacityLitres !== '' ? (float) $this->tankCapacityLitres : null,
            expectedLitresPerHour: $this->expectedLitresPerHour !== null && $this->expectedLitresPerHour !== '' ? (float) $this->expectedLitresPerHour : null,
            servesScope: $this->servesScope,
        ));

        $this->reset(['code', 'name', 'capacityKva', 'tankCapacityLitres', 'expectedLitresPerHour']);
        $this->toast(__('Generator registered.'));
    }

    public function render(): View
    {
        return view('utilities::generators.index', [
            'generators' => Generator::where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
