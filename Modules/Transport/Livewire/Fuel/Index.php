<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Fuel;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Transport\Domain\Actions\RecordFuelLogAction;
use Modules\Transport\Domain\DataObjects\RecordFuelLogData;
use Modules\Transport\Models\FuelLog;
use Modules\Transport\Models\Vehicle;

/**
 * `Fuel\Index` (Book H2 OPS-01 §5 ⭐/BR-OPS-01-013, `transport.fuel.record`).
 * A school-tank draw requires a store and item, so stock and vehicle
 * consumption reconcile (AC-OPS-01-007) — `RecordFuelLogAction` itself
 * refuses a school-tank source missing either.
 */
#[Title('Fuel log')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $vehicleId = null;

    public string $fuelledAt = '';

    public string $odometerKm = '';

    public string $litres = '';

    public string $unitPriceMinor = '';

    public string $source = 'filling_station';

    public ?int $storeId = null;

    public ?int $itemId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('transport.fuel.record');
        $this->fuelledAt = now()->toDateTimeString();
    }

    public function record(): void
    {
        $this->validate([
            'vehicleId' => ['required', 'integer'],
            'fuelledAt' => ['required', 'date'],
            'odometerKm' => ['required', 'numeric', 'gt:0'],
            'litres' => ['required', 'numeric', 'gt:0'],
            'unitPriceMinor' => ['required', 'integer', 'gt:0'],
        ]);

        try {
            $log = app(RecordFuelLogAction::class)->execute(new RecordFuelLogData(
                schoolId: $this->school->id,
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                vehicleId: (int) $this->vehicleId,
                fuelledAt: Carbon::parse($this->fuelledAt),
                odometerKm: (float) $this->odometerKm,
                litres: (float) $this->litres,
                unitPriceMinor: (int) $this->unitPriceMinor,
                currency: $this->school->base_currency,
                source: $this->source,
                authorisedByUserId: (int) auth()->id(),
                storeId: $this->storeId,
                itemId: $this->itemId,
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['odometerKm', 'litres', 'unitPriceMinor']);

        if ($log->is_anomaly) {
            $this->toast(__('Fuel logged — flagged as an anomaly, review required.'), 'warning');
        } else {
            $this->toast(__('Fuel logged.'));
        }
    }

    public function render(): View
    {
        return view('transport::fuel.index', [
            'logs' => FuelLog::with('vehicle')->where('school_id', $this->school->id)->orderByDesc('fuelled_at')->limit(50)->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->orderBy('fleet_number')->get(),
            'stores' => Store::where('school_id', $this->school->id)->orderBy('code')->get(),
            'items' => InventoryItem::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
        ]);
    }
}
