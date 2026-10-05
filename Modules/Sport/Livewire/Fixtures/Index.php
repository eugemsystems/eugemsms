<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Fixtures;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\RollCall;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Facilities\Models\BookableResource;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Sport\Domain\Actions\ConfirmFixtureAction;
use Modules\Sport\Domain\Actions\MarkSquadRollStatusForFixtureAction;
use Modules\Sport\Domain\Actions\RecordFixtureInjuryAction;
use Modules\Sport\Domain\Actions\RecordFixtureResultAction;
use Modules\Sport\Domain\Actions\ScheduleFixtureAction;
use Modules\Sport\Domain\Actions\SelectFixtureSquadAction;
use Modules\Sport\Domain\DataObjects\ConfirmFixtureData;
use Modules\Sport\Domain\DataObjects\MarkSquadRollStatusForFixtureData;
use Modules\Sport\Domain\DataObjects\RecordFixtureInjuryData;
use Modules\Sport\Domain\DataObjects\RecordFixtureResultData;
use Modules\Sport\Domain\DataObjects\ScheduleFixtureData;
use Modules\Sport\Domain\DataObjects\SelectFixtureSquadData;
use Modules\Sport\Domain\Exceptions\MedicalClearanceRequiredException;
use Modules\Sport\Models\Fixture;
use Modules\Sport\Models\Team;
use Modules\Transport\Models\Driver;
use Modules\Transport\Models\Vehicle;

/**
 * `Fixtures\Index` (Book H2 OPS-07 §4 ⭐/BR-OPS-07-005/006/007/012,
 * `activities.fixture.manage`). Folds the spec's own, separate
 * "Squad selection" and "Results" screens into this fixture's own
 * action bar — the same list+detail folding `Maintenance\WorkOrders\Index`
 * (Book H2 OPS-02) already established for this book. One fixture,
 * selected once: schedule, confirm (creates a real `OPS-01` trip for
 * an away game or an `OPS-05` booking for a home one), select squad
 * (medical clearance enforced, never just shown), mark `BRD-02` roll
 * status `fixture` for the squad, record the result, and record an
 * injury (`BRD-06`, guardian notified).
 */
#[Title('Fixtures')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $teamId = null;

    public string $opponent = '';

    public string $fixtureType = 'friendly';

    public string $venueType = 'home';

    public ?string $venueName = null;

    public string $fixtureDate = '';

    public ?string $startTime = null;

    public ?int $selectedFixtureId = null;

    public ?int $vehicleId = null;

    public ?int $driverId = null;

    public ?int $escortStaffId = null;

    public ?int $resourceId = null;

    /** @var array<int, int> */
    public array $squadStudentIds = [];

    /** @var array<int, int> */
    public array $rollCallIds = [];

    public string $result = 'won';

    public ?string $scoreFor = null;

    public ?string $scoreAgainst = null;

    public ?int $injuryStudentId = null;

    public string $injuryType = 'minor_injury';

    public string $injuryDescription = '';

    public string $injurySeverity = 'minor';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('activities.fixture.manage');
        $this->fixtureDate = Carbon::now()->toDateString();
    }

    public function schedule(): void
    {
        $this->validate([
            'teamId' => ['required', 'integer'],
            'opponent' => ['required', 'string'],
            'fixtureDate' => ['required', 'date'],
        ]);

        app(ScheduleFixtureAction::class)->execute(new ScheduleFixtureData(
            schoolId: $this->school->id,
            termId: (int) SessionContext::termId(),
            teamId: (int) $this->teamId,
            opponent: $this->opponent,
            fixtureType: $this->fixtureType,
            venueType: $this->venueType,
            fixtureDate: Carbon::parse($this->fixtureDate),
            venueName: $this->venueType === 'away' ? $this->venueName : null,
            startTime: $this->startTime,
        ));

        $this->reset(['opponent', 'venueName', 'startTime']);
        $this->toast(__('Fixture scheduled.'));
    }

    public function select(int $fixtureId): void
    {
        $this->selectedFixtureId = $fixtureId;
        $fixture = Fixture::findOrFail($fixtureId);
        $this->squadStudentIds = $fixture->squad_student_ids ?? [];
    }

    public function confirm(): void
    {
        if ($this->selectedFixtureId === null) {
            return;
        }

        app(ConfirmFixtureAction::class)->execute(new ConfirmFixtureData(
            fixtureId: $this->selectedFixtureId,
            confirmedByUserId: (int) auth()->id(),
            vehicleId: $this->vehicleId,
            driverId: $this->driverId,
            escortStaffId: $this->escortStaffId,
            resourceId: $this->resourceId,
        ));

        $this->toast(__('Fixture confirmed.'));
    }

    public function selectSquad(): void
    {
        if ($this->selectedFixtureId === null) {
            return;
        }

        try {
            app(SelectFixtureSquadAction::class)->execute(new SelectFixtureSquadData(
                fixtureId: $this->selectedFixtureId,
                squadStudentIds: array_map('intval', $this->squadStudentIds),
            ));
        } catch (MedicalClearanceRequiredException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Squad selected — guardians notified.'));
    }

    public function markRollStatus(): void
    {
        if ($this->selectedFixtureId === null || $this->rollCallIds === []) {
            return;
        }

        app(MarkSquadRollStatusForFixtureAction::class)->execute(new MarkSquadRollStatusForFixtureData(
            fixtureId: $this->selectedFixtureId,
            rollCallIds: array_map('intval', $this->rollCallIds),
            markedByUserId: (int) auth()->id(),
        ));

        $this->toast(__('Roll status set to fixture for the squad.'));
    }

    public function recordResult(): void
    {
        if ($this->selectedFixtureId === null) {
            return;
        }

        app(RecordFixtureResultAction::class)->execute(new RecordFixtureResultData(
            fixtureId: $this->selectedFixtureId,
            result: $this->result,
            scoreFor: $this->scoreFor,
            scoreAgainst: $this->scoreAgainst,
        ));

        $this->reset(['scoreFor', 'scoreAgainst']);
        $this->toast(__('Result recorded.'));
    }

    public function recordInjury(): void
    {
        if ($this->selectedFixtureId === null) {
            return;
        }

        $this->validate([
            'injuryStudentId' => ['required', 'integer'],
            'injuryDescription' => ['required', 'string'],
        ]);

        app(RecordFixtureInjuryAction::class)->execute(new RecordFixtureInjuryData(
            fixtureId: $this->selectedFixtureId,
            termId: (int) SessionContext::termId(),
            studentId: (int) $this->injuryStudentId,
            incidentType: $this->injuryType,
            occurredAt: Carbon::now(),
            description: $this->injuryDescription,
            severity: $this->injurySeverity,
            reportedByUserId: (int) auth()->id(),
        ));

        $this->reset(['injuryStudentId', 'injuryDescription']);
        $this->toast(__('Injury recorded — guardian notified.'));
    }

    public function render(): View
    {
        return view('sport::fixtures.index', [
            'fixtures' => Fixture::with('team.activity')->where('school_id', $this->school->id)->orderByDesc('fixture_date')->limit(30)->get(),
            'teams' => Team::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'vehicles' => Vehicle::where('school_id', $this->school->id)->where('is_active', true)->orderBy('fleet_number')->get(),
            'drivers' => Driver::with('staff')->where('school_id', $this->school->id)->where('status', 'active')->get(),
            'escorts' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->limit(100)->get(),
            'resources' => BookableResource::where('school_id', $this->school->id)->orderBy('name')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
            'rollCalls' => $this->selectedFixtureId !== null
                ? RollCall::where('school_id', $this->school->id)->whereDate('roll_date', Fixture::find($this->selectedFixtureId)?->fixture_date)->get()
                : collect(),
        ]);
    }
}
