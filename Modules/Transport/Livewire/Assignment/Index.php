<?php

declare(strict_types=1);

namespace Modules\Transport\Livewire\Assignment;

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
use Modules\People\Models\Student;
use Modules\Transport\Domain\Actions\AssignLearnerToRouteAction;
use Modules\Transport\Domain\DataObjects\AssignLearnerToRouteData;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;

/**
 * `Assignment\Index` (Book H2 OPS-01 §5 ⭐/BR-OPS-01-006/007/008,
 * `transport.assign`). Shows the resulting termly fee live as the
 * pickup stop (and therefore zone) is picked, per the spec's own
 * screen description — read directly from the stop's own zone, not a
 * `FIN-02` preview call, since zone-based proration itself stays a
 * documented `FIN-02` deferral (see `AssignLearnerToRouteAction`'s own
 * docblock: the `usage_based` billing basis doesn't exist yet).
 */
#[Title('Learner transport assignment')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $routeId = null;

    public ?int $pickupStopId = null;

    public string $direction = 'both';

    public string $effectiveFrom = '';

    public bool $authorisedByGuardian = false;

    public bool $overrideCapacity = false;

    public ?string $overrideReason = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('transport.assign');
        $this->effectiveFrom = now()->toDateString();
    }

    public function previewFeeMinor(): ?int
    {
        if ($this->pickupStopId === null) {
            return null;
        }

        $stop = RouteStop::with('zone')->find($this->pickupStopId);

        return $stop?->zone?->termly_fee_minor;
    }

    public function assign(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'routeId' => ['required', 'integer'],
            'pickupStopId' => ['required', 'integer'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        try {
            app(AssignLearnerToRouteAction::class)->execute(new AssignLearnerToRouteData(
                schoolId: $this->school->id,
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                studentId: (int) $this->studentId,
                routeId: (int) $this->routeId,
                pickupStopId: (int) $this->pickupStopId,
                direction: $this->direction,
                effectiveFrom: Carbon::parse($this->effectiveFrom),
                authorisedByGuardian: $this->authorisedByGuardian,
                overrideCapacity: $this->overrideCapacity,
                overrideReason: $this->overrideReason,
            ));
        } catch (ValidationException|DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'routeId', 'pickupStopId', 'authorisedByGuardian', 'overrideCapacity', 'overrideReason']);
        $this->toast(__('Learner assigned to route.'));
    }

    public function render(): View
    {
        return view('transport::assignment.index', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
            'routes' => Route::where('school_id', $this->school->id)->orderBy('code')->get(),
            'stops' => $this->routeId !== null ? RouteStop::with('zone')->where('route_id', $this->routeId)->orderBy('sequence')->get() : collect(),
            'assignments' => LearnerTransport::with(['route', 'zone'])->where('school_id', $this->school->id)->where('status', 'active')->orderByDesc('id')->limit(100)->get(),
            'previewFeeMinor' => $this->previewFeeMinor(),
        ]);
    }
}
