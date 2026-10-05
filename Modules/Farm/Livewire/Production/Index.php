<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\Production;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\RecordProductionOutputAction;
use Modules\Farm\Domain\DataObjects\RecordProductionOutputData;
use Modules\Farm\Models\ProductionOutput;
use Modules\Farm\Models\ProductionUnit;

/**
 * `Production\Index` (Book H2 OPS-03 §5/BR-OPS-03-015, `farm.record`).
 * Daily milk/eggs recording per unit per day — the
 * `UNIQUE(production_unit_id, output_date, output_type)` constraint is
 * what actually enforces "per day" (a duplicate submission surfaces as
 * a plain validation failure, not a special check this screen adds).
 */
#[Title('Daily production')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $productionUnitId = null;

    public string $outputType = 'milk';

    public string $quantity = '';

    public string $unit = 'litres';

    public string $destination = 'kitchen';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('farm.record');
    }

    public function record(): void
    {
        $this->validate([
            'productionUnitId' => ['required', 'integer'],
            'outputType' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            app(RecordProductionOutputAction::class)->execute(new RecordProductionOutputData(
                schoolId: $this->school->id,
                productionUnitId: (int) $this->productionUnitId,
                outputDate: Carbon::now(),
                outputType: $this->outputType,
                quantity: (float) $this->quantity,
                unit: $this->unit,
                currency: $this->school->base_currency,
                recordedByUserId: (int) auth()->id(),
                destination: $this->destination,
            ));
        } catch (QueryException) {
            $this->toast(__('A record for this unit/output type/day already exists.'), 'danger');

            return;
        }

        $this->reset(['quantity']);
        $this->toast(__('Production recorded.'));
    }

    public function render(): View
    {
        return view('farm::production.index', [
            'outputs' => ProductionOutput::with('productionUnit')->where('school_id', $this->school->id)->orderByDesc('output_date')->limit(100)->get(),
            'units' => ProductionUnit::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
