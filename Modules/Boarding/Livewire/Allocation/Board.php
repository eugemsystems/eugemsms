<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\AllocateBedAction;
use Modules\Boarding\Domain\Actions\EndBedAllocationAction;
use Modules\Boarding\Domain\Actions\MoveLearnerAction;
use Modules\Boarding\Domain\DataObjects\AllocateBedData;
use Modules\Boarding\Domain\DataObjects\EndBedAllocationData;
use Modules\Boarding\Domain\DataObjects\MoveLearnerData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Allocation\Board` (Book F BRD-01 §5 ⭐, `boarding.allocation.view` to
 * browse, `boarding.allocation.manage` to allocate/move/end). A plain
 * occupied/free bed table per hostel rather than the spec's own
 * drag-to-move visual grid — a deliberate simplification on its own
 * terms, not mirroring any other screen's trade-off (a later pass gave
 * `ACA-03 Timetable\Editor` real drag-to-move, so that screen is no
 * longer a same-shape precedent for this one). The server-side
 * constraint check is what matters and is real:
 * `AllocateBedAction`/`MoveLearnerAction` run their full
 * gender/incompatibility/room-service checks on every submit, with no
 * client-side bypass. Also stands in for the spec's separate "Bed
 * availability" report (the per-hostel free/occupied counts below).
 * Gender segregation has no override anywhere on this screen — a
 * mismatch throws `GenderMismatchException`, shown as a hard toast
 * error, never a warning with a way past it.
 */
#[Title('Occupancy board')]
#[Layout('layouts.app')]
final class Board extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $hostelId = null;

    public string $allocateStudentSearch = '';

    public ?int $allocateStudentId = null;

    public ?int $moveAllocationId = null;

    public ?int $moveToBedId = null;

    public string $moveReason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.allocation.view');
    }

    public function allocate(): void
    {
        $this->authorizePermission('boarding.allocation.manage');

        if ($this->allocateStudentId === null) {
            $this->toast(__('Pick a learner first.'), 'danger');

            return;
        }

        $year = $this->school->currentAcademicYear();
        $term = $year?->currentTerm();

        if ($year === null || $term === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        try {
            app(AllocateBedAction::class)->execute(new AllocateBedData(
                studentId: $this->allocateStudentId,
                academicYearId: $year->id,
                termId: $term->id,
                effectiveFrom: Carbon::now(),
                allocatedByUserId: (int) Auth::id(),
                candidateHostelIds: $this->hostelId !== null ? [$this->hostelId] : [],
                asDraft: false,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['allocateStudentSearch', 'allocateStudentId']);
        $this->toast(__('Bed allocated.'));
    }

    public function move(int $bedId): void
    {
        $this->authorizePermission('boarding.allocation.manage');

        $allocation = BedAllocation::findOrFail($this->moveAllocationId);

        if (trim($this->moveReason) === '') {
            $this->toast(__('A reason is required to move a learner.'), 'danger');

            return;
        }

        try {
            app(MoveLearnerAction::class)->execute(new MoveLearnerData(
                studentId: $allocation->student_id,
                newBedId: $bedId,
                effectiveFrom: Carbon::now(),
                reason: $this->moveReason,
                movedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['moveAllocationId', 'moveToBedId', 'moveReason']);
        $this->toast(__('Learner moved — history preserved.'));
    }

    public function endAllocation(int $allocationId, string $reason): void
    {
        $this->authorizePermission('boarding.allocation.manage');

        $allocation = BedAllocation::findOrFail($allocationId);

        app(EndBedAllocationAction::class)->execute(new EndBedAllocationData(
            studentId: $allocation->student_id,
            effectiveTo: Carbon::now(),
            reason: $reason,
        ));

        $this->toast(__('Allocation ended — bed released.'));
    }

    public function render(): View
    {
        $hostels = Hostel::where('school_id', $this->school->id)->orderBy('name')->get();
        $hostel = $this->hostelId !== null ? $hostels->firstWhere('id', $this->hostelId) : $hostels->first();

        $rooms = $hostel !== null ? $hostel->rooms()->with('beds')->get() : collect();

        $activeAllocationsByBedId = $hostel !== null
            ? BedAllocation::where('hostel_id', $hostel->id)
                ->where('status', 'confirmed')
                ->whereNull('effective_to')
                ->with('student')
                ->get()
                ->keyBy('bed_id')
            : collect();

        $searchResults = $this->allocateStudentSearch !== ''
            ? Student::where('school_id', $this->school->id)
                ->where(fn ($q) => $q->where('first_name', 'like', "%{$this->allocateStudentSearch}%")
                    ->orWhere('last_name', 'like', "%{$this->allocateStudentSearch}%")
                    ->orWhere('admission_number', 'like', "%{$this->allocateStudentSearch}%"))
                ->limit(10)->get()
            : collect();

        return view('boarding::allocation.board', [
            'hostels' => $hostels,
            'hostel' => $hostel,
            'rooms' => $rooms,
            'activeAllocationsByBedId' => $activeAllocationsByBedId,
            'searchResults' => $searchResults,
        ]);
    }
}
