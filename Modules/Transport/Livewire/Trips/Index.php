<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Trips;

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
use Modules\Transport\Domain\Actions\DepartTripAction;
use Modules\Transport\Domain\Actions\RecordTripOdometerAction;
use Modules\Transport\Domain\Actions\ScheduleTripAction;
use Modules\Transport\Domain\DataObjects\ScheduleTripData;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\Vehicle;

/**
 * `Trips\Index` (Book H2 OPS-01 §5 ⭐/BR-OPS-01-001/009/011,
 * `transport.trip.manage`). `ScheduleTripAction` itself refuses a
 * vehicle with expired compliance or a driver with expired documents,
 * naming the specific item (AC-OPS-01-001) — this screen surfaces
 * that refusal as a toast, it never duplicates the check.
 */
#[Title('Trip scheduling')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $tripDate = '';

    public string $tripType = 'route';

    public ?int $routeId = null;

    public ?int $vehicleId = null;

    public ?int $driverId = null;

    public ?string $purpose = null;

    public ?string $destination = null;

    public ?int $selectedTripId = null;

    public string $departureOdometer = '';

    public string $returnOdometer = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('transport.trip.manage');
        $this->tripDate = now()->toDateString();
    }

    public function schedule(): void
    {
        $this->validate([
            'tripDate' => ['required', 'date'],
            'vehicleId' => ['required', 'integer'],
            'driverId' => ['required', 'integer'],
        ]);

        try {
            app(ScheduleTripAction::class)->execute(new ScheduleTripData(
                schoolId: $this->school->id,
                termId: (int) SessionContext::termId(),
                tripDate: Carbon::parse($this->tripDate),
                tripType: $this->tripType,
                vehicleId: (int) $this->vehicleId,
                driverId: (int) $this->driverId,
                routeId: $this->routeId,
                purpose: $this->purpose,
                destination: $this->destination,
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['routeId', 'purpose', 'destination']);
        $this->toast(__('Trip scheduled.'));
    }

    public function select(int $tripId): void
    {
        $this->selectedTripId = $tripId;
    }

    public function depart(int $tripId): void
    {
        try {
            app(DepartTripAction::class)->execute($tripId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Trip departed — any learner still expected is flagged as a no-show.'));
    }

    public function recordDeparture(int $tripId): void
    {
        $this->validate(['departureOdometer' => ['required', 'numeric', 'gt:0']]);

        try {
            app(RecordTripOdometerAction::class)->execute($tripId, 'departure', (float) $this->departureOdometer, (int) auth()->id());
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->departureOdometer = '';
        $this->toast(__('Departure odometer recorded.'));
    }

    public function recordReturn(int $tripId): void
    {
        $this->validate(['returnOdometer' => ['required', 'numeric', 'gt:0']]);

        try {
            app(RecordTripOdometerAction::class)->execute($tripId, 'return', (float) $this->returnOdometer, (int) auth()->id());
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->returnOdometer = '';
        $this->toast(__('Return odometer recorded — distance computed, vehicle odometer advanced.'));
    }

    public function render(): View
    {
        return view('transport::trips.index', [
            'trips' => Trip::where('school_id', $this->school->id)->orderByDesc('trip_date')->limit(100)->get(),
            'selected' => $this->selectedTripId !== null ? Trip::where('school_id', $this->school->id)->find($this->selectedTripId) : null,
            'routes' => Route::where('school_id', $this->school->id)->orderBy('code')->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->where('status', 'active')->orderBy('fleet_number')->get(),
            'drivers' => Driver::with('staff')->where('school_id', $this->school->id)->where('status', 'active')->get(),
        ]);
    }
}
