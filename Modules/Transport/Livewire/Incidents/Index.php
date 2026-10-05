<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Incidents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Transport\Domain\Actions\ReportVehicleIncidentAction;
use Modules\Transport\Domain\DataObjects\ReportVehicleIncidentData;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleIncident;

/**
 * `Incidents\Index` (Book H2 OPS-01 §5/BR-OPS-01-017, `transport.incident.manage`).
 */
#[Title('Vehicle incidents')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $vehicleId = null;

    public string $incidentType = 'breakdown';

    public string $occurredAt = '';

    public string $location = '';

    public string $description = '';

    public bool $injuries = false;

    public ?int $estimatedDamageMinor = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.incident.manage');
        $this->occurredAt = now()->toDateTimeString();
    }

    public function report(): void
    {
        $this->validate([
            'vehicleId' => ['required', 'integer'],
            'occurredAt' => ['required', 'date'],
            'location' => ['required', 'string'],
            'description' => ['required', 'string'],
        ]);

        app(ReportVehicleIncidentAction::class)->execute(new ReportVehicleIncidentData(
            schoolId: $this->school->id,
            vehicleId: (int) $this->vehicleId,
            incidentType: $this->incidentType,
            occurredAt: Carbon::parse($this->occurredAt),
            location: $this->location,
            description: $this->description,
            reportedByUserId: (int) auth()->id(),
            injuries: $this->injuries,
            estimatedDamageMinor: $this->estimatedDamageMinor,
        ));

        $this->reset(['location', 'description', 'injuries', 'estimatedDamageMinor']);
        $this->toast(__('Incident reported.'));
    }

    public function render(): View
    {
        return view('transport::incidents.index', [
            'incidents' => VehicleIncident::with('vehicle')->where('school_id', $this->school->id)->orderByDesc('occurred_at')->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->orderBy('fleet_number')->get(),
        ]);
    }
}
