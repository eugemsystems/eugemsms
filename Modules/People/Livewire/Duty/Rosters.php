<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Duty;

use App\Concerns\Toasts;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateDutyRosterAction;
use Modules\People\Domain\Actions\GenerateDutyRosterAction;
use Modules\People\Domain\Actions\SwapDutyAssignmentAction;
use Modules\People\Domain\DataObjects\CreateDutyRosterData;
use Modules\People\Domain\DataObjects\DutySlot;
use Modules\People\Domain\DataObjects\GenerateDutyRosterData;
use Modules\People\Domain\DataObjects\SwapDutyAssignmentData;
use Modules\People\Models\DutyAssignment;
use Modules\People\Models\DutyRoster;
use Modules\People\Models\Staff;

/**
 * `People\Duty\Rosters` (Book C PPL-04 §5/BR-PPL-04-015/016,
 * `people.staff.duty_manage`). "Generate" takes a date range plus one
 * daily start/end time and builds one `DutySlot` per calendar day —
 * `GenerateDutyRosterAction`'s own fairness-balancing algorithm
 * (least-loaded-first, excluding staff on approved leave) does the
 * rest; this screen never reimplements that logic. Folds the spec's
 * separate "My duties" screen into this one (filter by staff in the
 * assignments table) rather than a near-duplicate screen — this pass
 * is internal-staff-admin-facing throughout, not self-service.
 */
#[Title('Duty rosters')]
#[Layout('layouts.app')]
final class Rosters extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $dutyType = 'teacher_on_duty';

    public string $rosterName = '';

    public string $rotationPattern = 'daily';

    public ?int $selectedRosterId = null;

    public string $generateFrom = '';

    public string $generateTo = '';

    public string $dailyStartTime = '07:00';

    public string $dailyEndTime = '16:00';

    public ?int $swapAssignmentId = null;

    public ?int $swapNewStaffId = null;

    public bool $swapConsented = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.staff.duty_manage');

        $this->generateFrom = now()->toDateString();
        $this->generateTo = now()->addWeek()->toDateString();
    }

    public function createRoster(): void
    {
        $this->validate([
            'dutyType' => ['required', 'string'],
            'rosterName' => ['required', 'string', 'max:120'],
            'rotationPattern' => ['required', 'in:daily,weekly,weekend,custom'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('rosterName', __('No active academic year/term is set for this school.'));

            return;
        }

        $roster = app(CreateDutyRosterAction::class)->execute(new CreateDutyRosterData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            dutyType: $this->dutyType,
            name: $this->rosterName,
            rotationPattern: $this->rotationPattern,
        ));

        $this->selectedRosterId = $roster->id;
        $this->rosterName = '';
        $this->toast(__('Roster created.'));
    }

    public function generate(): void
    {
        $this->validate([
            'selectedRosterId' => ['required', 'integer'],
            'generateFrom' => ['required', 'date'],
            'generateTo' => ['required', 'date', 'after_or_equal:generateFrom'],
            'dailyStartTime' => ['required'],
            'dailyEndTime' => ['required'],
        ]);

        $slots = [];

        foreach (CarbonPeriod::create($this->generateFrom, $this->generateTo) as $day) {
            $slots[] = new DutySlot(
                startsAt: Carbon::parse($day->toDateString().' '.$this->dailyStartTime),
                endsAt: Carbon::parse($day->toDateString().' '.$this->dailyEndTime),
            );
        }

        try {
            $result = app(GenerateDutyRosterAction::class)->execute(new GenerateDutyRosterData(
                rosterId: (int) $this->selectedRosterId,
                slots: $slots,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $assigned = count($result['assignments']);
        $skipped = count($result['skippedSlots']);
        $this->toast(__(':assigned slot(s) assigned, :skipped skipped (no eligible staff available).', ['assigned' => $assigned, 'skipped' => $skipped]));
    }

    public function swap(): void
    {
        $this->validate([
            'swapAssignmentId' => ['required', 'integer'],
            'swapNewStaffId' => ['required', 'integer'],
        ]);

        try {
            app(SwapDutyAssignmentAction::class)->execute(new SwapDutyAssignmentData(
                assignmentId: (int) $this->swapAssignmentId,
                newStaffId: (int) $this->swapNewStaffId,
                approvedByUserId: (int) Auth::id(),
                bothPartiesConsented: $this->swapConsented,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['swapAssignmentId', 'swapNewStaffId', 'swapConsented']);
        $this->toast(__('Duty swapped.'));
    }

    public function render(): View
    {
        return view('people::duty.rosters', [
            'rosters' => DutyRoster::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'assignments' => $this->selectedRosterId !== null
                ? DutyAssignment::where('roster_id', $this->selectedRosterId)->with('staff')->orderBy('starts_at')->get()
                : collect(),
            'staffList' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
        ]);
    }
}
