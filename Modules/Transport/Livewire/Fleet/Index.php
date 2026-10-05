<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Fleet;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Domain\Actions\CreateVehicleAction;
use Modules\Transport\Domain\Actions\GroundVehicleAction;
use Modules\Transport\Domain\Actions\ReactivateVehicleAction;
use Modules\Transport\Domain\DataObjects\CreateVehicleData;
use Modules\Transport\Models\Vehicle;

/**
 * `Fleet\Index` (Book H2 OPS-01 §5, `transport.view`/`.manage`).
 * Grounding/reactivation live here rather than a separate screen —
 * `ReactivateVehicleAction` is this pass's own gap-fill (see its
 * docblock): `GroundVehicleAction` shipped with no reverse, which
 * would otherwise leave this screen's own "grounded" filter a
 * one-way trip.
 */
#[Title('Fleet register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $fleetNumber = '';

    public string $registrationNumber = '';

    public string $vehicleType = 'bus';

    public ?int $seatingCapacity = null;

    public int $standingCapacity = 0;

    public string $fuelType = 'diesel';

    public ?int $costCentreId = null;

    public ?float $tankCapacityLitres = null;

    public ?float $expectedKmPerLitre = null;

    /** @var array<int, string> */
    public array $groundReason = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.view');
    }

    public function create(): void
    {
        $this->authorizePermission('transport.manage');

        $this->validate([
            'fleetNumber' => ['required', 'string', 'max:20'],
            'registrationNumber' => ['required', 'string', 'max:20'],
            'seatingCapacity' => ['required', 'integer', 'gt:0'],
            'costCentreId' => ['required', 'integer'],
        ]);

        app(CreateVehicleAction::class)->execute(new CreateVehicleData(
            schoolId: $this->school->id,
            fleetNumber: $this->fleetNumber,
            registrationNumber: $this->registrationNumber,
            vehicleType: $this->vehicleType,
            seatingCapacity: (int) $this->seatingCapacity,
            fuelType: $this->fuelType,
            costCentreId: (int) $this->costCentreId,
            standingCapacity: $this->standingCapacity,
            tankCapacityLitres: $this->tankCapacityLitres,
            expectedKmPerLitre: $this->expectedKmPerLitre,
        ));

        $this->reset(['fleetNumber', 'registrationNumber', 'tankCapacityLitres', 'expectedKmPerLitre']);
        $this->toast(__('Vehicle registered.'));
    }

    public function ground(int $vehicleId): void
    {
        $this->authorizePermission('transport.manage');

        $reason = $this->groundReason[$vehicleId] ?? '';

        if (trim($reason) === '') {
            $this->toast(__('A reason is required to ground a vehicle.'), 'danger');

            return;
        }

        app(GroundVehicleAction::class)->execute($vehicleId, $reason);
        $this->toast(__('Vehicle grounded.'));
    }

    public function reactivate(int $vehicleId): void
    {
        $this->authorizePermission('transport.manage');

        try {
            app(ReactivateVehicleAction::class)->execute($vehicleId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Vehicle reactivated.'));
    }

    public function render(): View
    {
        return view('transport::fleet.index', [
            'vehicles' => Vehicle::where('school_id', $this->school->id)->orderBy('fleet_number')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
