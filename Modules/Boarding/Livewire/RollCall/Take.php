<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\RollCall;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CompleteRollCallAction;
use Modules\Boarding\Domain\Actions\MarkRollCallAction;
use Modules\Boarding\Domain\Actions\OpenRollCallAction;
use Modules\Boarding\Domain\DataObjects\CompleteRollCallData;
use Modules\Boarding\Domain\DataObjects\MarkRollCallData;
use Modules\Boarding\Domain\DataObjects\OpenRollCallData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\RollCall;
use Modules\Boarding\Models\RollCallPoint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `RollCall\Take` (Book F BRD-02 §6 ⭐, `boarding.rollcall.conduct`).
 * One lifecycle screen: open → mark → complete. Pre-populated
 * statuses show their source reference so the housemaster sees *why*
 * a learner is accounted for (BR-BRD-02-004); overriding one requires
 * a note — `MarkRollCallAction` itself refuses the override without
 * one, this screen just surfaces that refusal as a toast. No offline
 * queue UI is built here (BR-BRD-02-005/006 are a mobile/API concern
 * per `BoardingServiceProvider`'s own docblock) — `MarkRollCallAction`'s
 * `updateOrCreate` idempotency is what actually matters and is real
 * regardless of which client calls it.
 */
#[Title('Take roll call')]
#[Layout('layouts.app')]
final class Take extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $hostelId = null;

    public ?int $rollCallPointId = null;

    public ?int $activeRollCallId = null;

    /** @var array<int, string> */
    public array $overrideNotes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.rollcall.conduct');
    }

    public function open(): void
    {
        if ($this->hostelId === null || $this->rollCallPointId === null) {
            $this->toast(__('Pick a hostel and a roll call point.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        $rollCall = app(OpenRollCallAction::class)->execute(new OpenRollCallData(
            rollCallPointId: $this->rollCallPointId,
            hostelId: $this->hostelId,
            termId: $term->id,
            rollDate: Carbon::now(),
        ));

        $this->activeRollCallId = $rollCall->id;
        $this->toast(__('Roll call opened — :expected expected.', ['expected' => $rollCall->expected_count]));
    }

    public function mark(int $studentId, string $status): void
    {
        if ($this->activeRollCallId === null) {
            return;
        }

        try {
            app(MarkRollCallAction::class)->execute(new MarkRollCallData(
                rollCallId: $this->activeRollCallId,
                studentId: $studentId,
                status: $status,
                markedByUserId: (int) Auth::id(),
                note: $this->overrideNotes[$studentId] ?? null,
            ));
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        if ($status === 'missing') {
            $this->toast(__('Marked missing — an incident has opened and escalation has started.'), 'danger');

            return;
        }

        $this->toast(__('Marked :status.', ['status' => $status]));
    }

    public function markAllPresentRemaining(): void
    {
        if ($this->activeRollCallId === null) {
            return;
        }

        $rollCall = RollCall::findOrFail($this->activeRollCallId);
        $marked = $rollCall->records()->pluck('student_id');

        $allocated = BedAllocation::where('hostel_id', $rollCall->hostel_id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->whereNotIn('student_id', $marked)
            ->pluck('student_id');

        foreach ($allocated as $studentId) {
            app(MarkRollCallAction::class)->execute(new MarkRollCallData(
                rollCallId: $rollCall->id,
                studentId: $studentId,
                status: 'present',
                markedByUserId: (int) Auth::id(),
            ));
        }

        $this->toast(__('Remaining learners marked present.'));
    }

    public function complete(): void
    {
        if ($this->activeRollCallId === null) {
            return;
        }

        app(CompleteRollCallAction::class)->execute(new CompleteRollCallData(
            rollCallId: $this->activeRollCallId,
            completedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Roll call completed.'));
        $this->activeRollCallId = null;
    }

    public function render(): View
    {
        $rollCall = $this->activeRollCallId !== null
            ? RollCall::with('records.student')->find($this->activeRollCallId)
            : null;

        $roster = $rollCall !== null
            ? BedAllocation::where('hostel_id', $rollCall->hostel_id)
                ->where('status', 'confirmed')
                ->whereNull('effective_to')
                ->with('student')
                ->get()
            : collect();

        $recordsByStudentId = $rollCall?->records->keyBy('student_id') ?? collect();

        return view('boarding::rollcall.take', [
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'points' => RollCallPoint::where('school_id', $this->school->id)->where('is_active', true)->get(),
            'rollCall' => $rollCall,
            'roster' => $roster,
            'recordsByStudentId' => $recordsByStudentId,
        ]);
    }
}
