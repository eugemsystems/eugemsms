<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\GeneratorRuns;

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
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Utilities\Domain\Actions\StartGeneratorRunAction;
use Modules\Utilities\Domain\Actions\StopGeneratorRunAction;
use Modules\Utilities\Domain\DataObjects\StartGeneratorRunData;
use Modules\Utilities\Domain\DataObjects\StopGeneratorRunData;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;

/**
 * `GeneratorRuns\Index` (Book H2 OPS-04 §6 ⭐/BR-OPS-04-011/012/013,
 * `utilities.generator.record`). Start, stop, diesel, reason — the
 * screen that feeds `FIN-10` depreciation and `OPS-02` usage-based
 * service schedules through `StopGeneratorRunAction` itself.
 */
#[Title('Generator log')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $generatorId = null;

    public string $reason = 'load_shedding';

    public ?string $loadSheddingStage = null;

    public ?int $stoppingRunId = null;

    public ?string $dieselLitres = null;

    public ?string $dieselUnitPriceMinor = null;

    public ?int $storeId = null;

    public ?int $itemId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('utilities.generator.record');
    }

    public function start(): void
    {
        $this->validate([
            'generatorId' => ['required', 'integer'],
            'reason' => ['required', 'string'],
        ]);

        app(StartGeneratorRunAction::class)->execute(new StartGeneratorRunData(
            schoolId: $this->school->id,
            termId: (int) SessionContext::termId(),
            generatorId: (int) $this->generatorId,
            startedAt: Carbon::now(),
            reason: $this->reason,
            loadSheddingStage: $this->loadSheddingStage !== '' ? $this->loadSheddingStage : null,
            operatedByUserId: (int) auth()->id(),
        ));

        $this->toast(__('Generator run started.'));
    }

    public function selectForStop(int $runId): void
    {
        $this->stoppingRunId = $runId;
    }

    public function stop(): void
    {
        if ($this->stoppingRunId === null) {
            return;
        }

        app(StopGeneratorRunAction::class)->execute($this->stoppingRunId, new StopGeneratorRunData(
            academicYearId: (int) SessionContext::yearId(),
            stoppedAt: Carbon::now(),
            dieselLitres: $this->dieselLitres !== null && $this->dieselLitres !== '' ? (float) $this->dieselLitres : null,
            dieselUnitPriceMinor: $this->dieselUnitPriceMinor !== null && $this->dieselUnitPriceMinor !== '' ? (int) $this->dieselUnitPriceMinor : null,
            currency: $this->school->base_currency,
            storeId: $this->storeId,
            itemId: $this->itemId,
            operatedByUserId: (int) auth()->id(),
        ));

        $this->reset(['stoppingRunId', 'dieselLitres', 'dieselUnitPriceMinor', 'storeId', 'itemId']);
        $this->toast(__('Generator run stopped.'));
    }

    public function render(): View
    {
        return view('utilities::generator-runs.index', [
            'runs' => GeneratorRun::with('generator')->where('school_id', $this->school->id)->orderByDesc('started_at')->limit(50)->get(),
            'generators' => Generator::where('school_id', $this->school->id)->orderBy('code')->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
