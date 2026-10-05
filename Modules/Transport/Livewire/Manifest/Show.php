<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Manifest;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Transport\Domain\Actions\RecordBoardingAction;
use Modules\Transport\Models\Trip;
use Modules\Transport\Models\TripPassenger;

/**
 * `Manifest\Show` (Book H2 OPS-01 §5/BR-OPS-01-009/010, `transport.drive`).
 * Tap-to-board/alight for today's trips — mobile-first in spirit, same
 * admin-console stand-in every other module's own driver/technician
 * screen uses in this pass (matching `Maintenance\WorkOrders\Index`'s
 * own job-card fold) since no dedicated mobile client exists in this
 * codebase yet.
 */
#[Title('Driver manifest')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $tripId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('transport.drive');
    }

    public function selectTrip(int $tripId): void
    {
        $this->tripId = $tripId;
    }

    public function board(int $tripPassengerId): void
    {
        app(RecordBoardingAction::class)->execute($tripPassengerId, 'boarded', 'manual');
        $this->toast(__('Boarded.'));
    }

    public function alight(int $tripPassengerId): void
    {
        app(RecordBoardingAction::class)->execute($tripPassengerId, 'alighted');
        $this->toast(__('Alighted.'));
    }

    public function render(): View
    {
        $trips = Trip::where('school_id', $this->school->id)
            ->whereDate('trip_date', now()->toDateString())
            ->where('trip_type', 'route')
            ->get();

        $passengers = $this->tripId !== null
            ? TripPassenger::with('student')->where('trip_id', $this->tripId)->get()
            : collect();

        return view('transport::manifest.show', [
            'trips' => $trips,
            'passengers' => $passengers,
        ]);
    }
}
