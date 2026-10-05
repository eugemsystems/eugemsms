<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\Solar;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Utilities\Domain\Actions\CreateSolarInstallationAction;
use Modules\Utilities\Domain\Actions\RecordSolarGenerationAction;
use Modules\Utilities\Domain\DataObjects\CreateSolarInstallationData;
use Modules\Utilities\Domain\DataObjects\RecordSolarGenerationData;
use Modules\Utilities\Models\SolarGeneration;
use Modules\Utilities\Models\SolarInstallation;

/**
 * `Solar\Index` (Book H2 OPS-04 §6/BR-OPS-04-014, `utilities.manage`).
 * Generation, offset, and avoided cost — `grid_offset_kwh` is what
 * `RecordSolarGenerationAction` itself derives (generated output
 * capped at same-day consumption), not a figure this screen computes.
 */
#[Title('Solar')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $capacityKwp = '';

    public ?string $batteryCapacityKwh = null;

    public ?int $installationId = null;

    public string $recordDate = '';

    public string $kwhGenerated = '';

    public ?string $kwhConsumed = null;

    public ?string $batteryStatePercent = null;

    public string $readingMethod = 'manual';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.manage');
        $this->recordDate = Carbon::now()->toDateString();
    }

    public function createInstallation(): void
    {
        $this->validate([
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'capacityKwp' => ['required', 'numeric', 'gt:0'],
        ]);

        app(CreateSolarInstallationAction::class)->execute(new CreateSolarInstallationData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            capacityKwp: (float) $this->capacityKwp,
            batteryCapacityKwh: $this->batteryCapacityKwh !== null && $this->batteryCapacityKwh !== '' ? (float) $this->batteryCapacityKwh : null,
        ));

        $this->reset(['code', 'name', 'capacityKwp', 'batteryCapacityKwh']);
        $this->toast(__('Solar installation registered.'));
    }

    public function recordGeneration(): void
    {
        $this->validate([
            'installationId' => ['required', 'integer'],
            'recordDate' => ['required', 'date'],
            'kwhGenerated' => ['required', 'numeric', 'gt:0'],
        ]);

        app(RecordSolarGenerationAction::class)->execute(new RecordSolarGenerationData(
            schoolId: $this->school->id,
            installationId: (int) $this->installationId,
            recordDate: Carbon::parse($this->recordDate),
            kwhGenerated: (float) $this->kwhGenerated,
            readingMethod: $this->readingMethod,
            kwhConsumed: $this->kwhConsumed !== null && $this->kwhConsumed !== '' ? (float) $this->kwhConsumed : null,
            batteryStatePercent: $this->batteryStatePercent !== null && $this->batteryStatePercent !== '' ? (float) $this->batteryStatePercent : null,
            recordedByUserId: (int) auth()->id(),
        ));

        $this->reset(['kwhGenerated', 'kwhConsumed', 'batteryStatePercent']);
        $this->toast(__('Generation recorded.'));
    }

    public function render(): View
    {
        return view('utilities::solar.index', [
            'installations' => SolarInstallation::where('school_id', $this->school->id)->orderBy('code')->get(),
            'generation' => SolarGeneration::with('installation')->where('school_id', $this->school->id)->orderByDesc('record_date')->limit(30)->get(),
        ]);
    }
}
