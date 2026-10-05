<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Water;

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
use Modules\Finance\Models\CostCentre;
use Modules\Utilities\Domain\Actions\CreateWaterSourceAction;
use Modules\Utilities\Domain\Actions\RecordWaterQualityTestAction;
use Modules\Utilities\Domain\Actions\RecordWaterReadingAction;
use Modules\Utilities\Domain\DataObjects\CreateWaterSourceData;
use Modules\Utilities\Domain\DataObjects\RecordWaterQualityTestData;
use Modules\Utilities\Domain\DataObjects\RecordWaterReadingData;
use Modules\Utilities\Models\WaterReading;
use Modules\Utilities\Models\WaterSource;

/**
 * `Water\Index` (Book H2 OPS-04 §6/BR-OPS-04-015/016/017,
 * `utilities.water.manage`). Sources, storage, yield, quality — water
 * is a boarding-viability issue here, not a utility line item
 * (`RecordWaterReadingAction`'s own docblock).
 */
#[Title('Water')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $sourceType = 'borehole';

    public ?string $yieldLitresPerHour = null;

    public ?string $storageCapacityLitres = null;

    public ?int $waterSourceId = null;

    public string $readOn = '';

    public ?string $storageLevelPercent = null;

    public ?string $volumePumpedLitres = null;

    public ?string $yieldObserved = null;

    public ?int $costCentreId = null;

    public ?int $qualitySourceId = null;

    public string $qualityStatus = 'potable';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('utilities.water.manage');
        $this->readOn = Carbon::now()->toDateString();
    }

    public function createSource(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'sourceType' => ['required', 'string'],
        ]);

        app(CreateWaterSourceAction::class)->execute(new CreateWaterSourceData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            sourceType: $this->sourceType,
            yieldLitresPerHour: $this->yieldLitresPerHour !== null && $this->yieldLitresPerHour !== '' ? (float) $this->yieldLitresPerHour : null,
            storageCapacityLitres: $this->storageCapacityLitres !== null && $this->storageCapacityLitres !== '' ? (float) $this->storageCapacityLitres : null,
        ));

        $this->reset(['code', 'name', 'yieldLitresPerHour', 'storageCapacityLitres']);
        $this->toast(__('Water source registered.'));
    }

    public function recordReading(): void
    {
        $this->validate([
            'waterSourceId' => ['required', 'integer'],
            'readOn' => ['required', 'date'],
        ]);

        app(RecordWaterReadingAction::class)->execute(new RecordWaterReadingData(
            schoolId: $this->school->id,
            waterSourceId: (int) $this->waterSourceId,
            readOn: Carbon::parse($this->readOn),
            readByUserId: (int) auth()->id(),
            storageLevelPercent: $this->storageLevelPercent !== null && $this->storageLevelPercent !== '' ? (float) $this->storageLevelPercent : null,
            volumePumpedLitres: $this->volumePumpedLitres !== null && $this->volumePumpedLitres !== '' ? (float) $this->volumePumpedLitres : null,
            yieldObserved: $this->yieldObserved !== null && $this->yieldObserved !== '' ? (float) $this->yieldObserved : null,
            academicYearId: SessionContext::yearId(),
            termId: SessionContext::termId(),
            costCentreId: $this->costCentreId,
        ));

        $this->reset(['storageLevelPercent', 'volumePumpedLitres', 'yieldObserved']);
        $this->toast(__('Water reading recorded.'));
    }

    public function recordQualityTest(): void
    {
        $this->validate([
            'qualitySourceId' => ['required', 'integer'],
            'qualityStatus' => ['required', 'string'],
        ]);

        app(RecordWaterQualityTestAction::class)->execute(new RecordWaterQualityTestData(
            waterSourceId: (int) $this->qualitySourceId,
            testedOn: Carbon::now(),
            qualityStatus: $this->qualityStatus,
        ));

        if ($this->qualityStatus === 'not_potable') {
            $this->toast(__('Not potable — nurse and catering manager alerted.'), 'danger');
        } else {
            $this->toast(__('Water quality test recorded.'));
        }
    }

    public function render(): View
    {
        return view('utilities::water.index', [
            'sources' => WaterSource::where('school_id', $this->school->id)->orderBy('code')->get(),
            'readings' => WaterReading::with('waterSource')->where('school_id', $this->school->id)->orderByDesc('read_on')->limit(30)->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
