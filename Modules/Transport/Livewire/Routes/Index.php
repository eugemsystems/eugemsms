<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Routes;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Finance\Models\CostCentre;
use Modules\Transport\Domain\Actions\CreateRouteAction;
use Modules\Transport\Domain\Actions\CreateTransportZoneAction;
use Modules\Transport\Domain\DataObjects\CreateRouteData;
use Modules\Transport\Domain\DataObjects\CreateTransportZoneData;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\TransportZone;
use Modules\Transport\Models\Vehicle;

/**
 * `Routes\Index` (Book H2 OPS-01 §5, `transport.manage`). Folds zone
 * management in alongside route+stop creation, matching the spec's
 * own "Routes & stops | ... | zone assignment" screen description —
 * the spec names no separate "Zones" screen anywhere in its own §5
 * table.
 */
#[Title('Routes & zones')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $zoneCode = '';

    public string $zoneName = '';

    public string $zoneTermlyFeeMinor = '';

    public ?float $zoneMaxDistanceKm = null;

    public string $routeCode = '';

    public string $routeName = '';

    public string $direction = 'both';

    public ?int $capacity = null;

    public ?int $costCentreId = null;

    public ?int $assignedVehicleId = null;

    public ?int $assignedDriverId = null;

    /** @var array<int, array{name: string, landmark: string, zoneId: string, distanceFromSchoolKm: string}> */
    public array $stops = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('transport.manage');
        $this->addStop();
    }

    public function addStop(): void
    {
        $this->stops[] = ['name' => '', 'landmark' => '', 'zoneId' => '', 'distanceFromSchoolKm' => ''];
    }

    public function removeStop(int $index): void
    {
        unset($this->stops[$index]);
        $this->stops = array_values($this->stops);
    }

    public function createZone(): void
    {
        $this->validate([
            'zoneCode' => ['required', 'string', 'max:20'],
            'zoneName' => ['required', 'string', 'max:120'],
            'zoneTermlyFeeMinor' => ['required', 'integer', 'gt:0'],
        ]);

        app(CreateTransportZoneAction::class)->execute(new CreateTransportZoneData(
            schoolId: $this->school->id,
            code: $this->zoneCode,
            name: $this->zoneName,
            termlyFeeMinor: (int) $this->zoneTermlyFeeMinor,
            currency: $this->school->base_currency,
            maxDistanceKm: $this->zoneMaxDistanceKm,
        ));

        $this->reset(['zoneCode', 'zoneName', 'zoneTermlyFeeMinor', 'zoneMaxDistanceKm']);
        $this->toast(__('Transport zone created.'));
    }

    public function createRoute(): void
    {
        $this->validate([
            'routeCode' => ['required', 'string', 'max:20'],
            'routeName' => ['required', 'string', 'max:150'],
            'capacity' => ['required', 'integer', 'gt:0'],
            'costCentreId' => ['required', 'integer'],
            'stops' => ['array', 'min:1'],
            'stops.*.name' => ['required', 'string'],
        ]);

        app(CreateRouteAction::class)->execute(new CreateRouteData(
            schoolId: $this->school->id,
            academicYearId: (int) SessionContext::yearId(),
            code: $this->routeCode,
            name: $this->routeName,
            direction: $this->direction,
            capacity: (int) $this->capacity,
            costCentreId: (int) $this->costCentreId,
            stops: collect($this->stops)->map(fn (array $s): array => [
                'name' => $s['name'],
                'landmark' => $s['landmark'] !== '' ? $s['landmark'] : null,
                'zoneId' => $s['zoneId'] !== '' ? (int) $s['zoneId'] : null,
                'distanceFromSchoolKm' => $s['distanceFromSchoolKm'] !== '' ? (float) $s['distanceFromSchoolKm'] : null,
            ])->all(),
            assignedVehicleId: $this->assignedVehicleId,
            assignedDriverId: $this->assignedDriverId,
        ));

        $this->reset(['routeCode', 'routeName', 'capacity', 'costCentreId', 'assignedVehicleId', 'assignedDriverId', 'stops']);
        $this->addStop();
        $this->toast(__('Route created.'));
    }

    public function render(): View
    {
        return view('transport::routes.index', [
            'zones' => TransportZone::where('school_id', $this->school->id)->orderBy('code')->get(),
            'routes' => Route::with('stops')->where('school_id', $this->school->id)->orderBy('code')->get(),
            'costCentres' => CostCentre::where('school_id', $this->school->id)->orderBy('code')->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->orderBy('fleet_number')->get(),
            'drivers' => Driver::with('staff')->where('school_id', $this->school->id)->orderBy('id')->get(),
        ]);
    }
}
