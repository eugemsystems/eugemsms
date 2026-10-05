<?php

declare(strict_types=1);

namespace Modules\Facilities\Livewire\Resources;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Facilities\Domain\Actions\CreateBookableResourceAction;
use Modules\Facilities\Domain\DataObjects\CreateBookableResourceData;
use Modules\Facilities\Models\BookableResource;
use Modules\Finance\Models\CostCentre;

/**
 * `Resources\Index` (Book H2 OPS-05 §4, `facilities.manage`). A
 * gap-filling screen — the spec's own screen table names no screen
 * that creates a `BookableResource` row, even though
 * `CreateBookableResourceAction` exists (see `.ai/rules/facilities.md`).
 * Register once here; every other screen in this module reads from
 * this register.
 */
#[Title('Bookable resources')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $resourceType = 'hall';

    public ?int $capacity = null;

    public bool $isExternallyHireable = false;

    public ?string $hireRateMinor = null;

    public string $hireRateUnit = 'hour';

    public ?string $depositMinor = null;

    public int $requiresSetupMinutes = 0;

    public int $requiresCleaningMinutes = 0;

    public int $bookingLeadTimeHours = 24;

    public ?int $costCentreId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('facilities.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'resourceType' => ['required', 'string'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateBookableResourceAction::class)->execute(new CreateBookableResourceData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            resourceType: $this->resourceType,
            costCentreId: (int) $this->costCentreId,
            capacity: $this->capacity,
            isExternallyHireable: $this->isExternallyHireable,
            hireRateMinor: $this->hireRateMinor !== null && $this->hireRateMinor !== '' ? (int) $this->hireRateMinor : null,
            hireRateUnit: $this->isExternallyHireable ? $this->hireRateUnit : null,
            hireCurrency: $this->isExternallyHireable ? $this->school->base_currency : null,
            depositMinor: $this->depositMinor !== null && $this->depositMinor !== '' ? (int) $this->depositMinor : null,
            requiresSetupMinutes: $this->requiresSetupMinutes,
            requiresCleaningMinutes: $this->requiresCleaningMinutes,
            bookingLeadTimeHours: $this->bookingLeadTimeHours,
        ));

        $this->reset(['code', 'name', 'capacity', 'isExternallyHireable', 'hireRateMinor', 'depositMinor', 'requiresSetupMinutes', 'requiresCleaningMinutes']);
        $this->toast(__('Bookable resource created.'));
    }

    public function render(): View
    {
        return view('facilities::resources.index', [
            'resources' => BookableResource::where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
