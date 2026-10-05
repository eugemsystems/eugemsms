<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Harvest;

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
use Modules\Farm\Domain\Actions\RecordHarvestAction;
use Modules\Farm\Domain\DataObjects\RecordHarvestData;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\Harvest;
use Modules\Finance\Models\Account;
use Modules\Stores\Models\InventoryItem;

/**
 * `Harvest\Index` (Book H2 OPS-03 §5 ⭐⭐/BR-OPS-03-005/006/AC-OPS-03-001,
 * `farm.record`). Cost per kg is shown immediately after recording,
 * read straight back off the cycle `RecordHarvestAction` just updated
 * — never recomputed independently here.
 */
#[Title('Harvest')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $cropCycleId = null;

    public string $quantityKg = '';

    public ?int $itemId = null;

    public ?int $farmProductionContraAccountId = null;

    public string $destination = 'store';

    public ?string $qualityGrade = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('farm.record');
    }

    public function record(): void
    {
        $this->validate([
            'cropCycleId' => ['required', 'integer'],
            'quantityKg' => ['required', 'numeric', 'gt:0'],
            'itemId' => ['required', 'integer'],
            'farmProductionContraAccountId' => ['required', 'integer'],
        ]);

        $harvest = app(RecordHarvestAction::class)->execute(new RecordHarvestData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            termId: (int) SessionContext::termId(),
            cropCycleId: (int) $this->cropCycleId,
            harvestedOn: Carbon::now(),
            quantityKg: (float) $this->quantityKg,
            itemId: (int) $this->itemId,
            farmProductionContraAccountId: (int) $this->farmProductionContraAccountId,
            recordedByUserId: (int) auth()->id(),
            destination: $this->destination,
            qualityGrade: $this->qualityGrade,
        ));

        $this->reset(['quantityKg', 'qualityGrade']);
        $this->toast(__('Harvest recorded — cost per kg: :cost', ['cost' => number_format($harvest->unit_cost_minor / 100, 2)]));
    }

    public function render(): View
    {
        return view('farm::harvest.index', [
            'cycles' => CropCycle::where('school_id', $this->school->id)->whereIn('status', ['growing', 'harvesting', 'harvested'])->get(),
            'harvests' => Harvest::with('cropCycle')->where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
