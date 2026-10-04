<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\AddToWaitingListAction;
use Modules\Boarding\Domain\Actions\ReevaluateWaitingListAction;
use Modules\Boarding\Domain\DataObjects\AddToWaitingListData;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelWaitingListEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Allocation\Waitlist` (Book F BRD-01 §5, `boarding.allocation.manage`).
 * `ReevaluateWaitingListAction`'s "offer with an expiry" half is
 * deliberately deferred in the domain layer itself (see that action's
 * own docblock — no notification wiring yet); this screen only
 * recomputes `position`, matching what actually exists.
 */
#[Title('Waiting list')]
#[Layout('layouts.app')]
final class Waitlist extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $preferredHostelId = null;

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('boarding.allocation.manage');
    }

    public function add(): void
    {
        $year = $this->school->currentAcademicYear();
        $term = $year?->currentTerm();

        if ($year === null || $term === null || $this->studentId === null) {
            $this->toast(__('Pick a learner and ensure a current term is set.'), 'danger');

            return;
        }

        app(AddToWaitingListAction::class)->execute(new AddToWaitingListData(
            schoolId: $this->school->id,
            academicYearId: $year->id,
            termId: $term->id,
            studentId: $this->studentId,
            preferredHostelId: $this->preferredHostelId,
            reason: $this->reason !== '' ? $this->reason : null,
        ));

        $this->reset(['studentId', 'preferredHostelId', 'reason']);
        $this->toast(__('Added to the waiting list.'));
    }

    public function reevaluate(): void
    {
        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            return;
        }

        app(ReevaluateWaitingListAction::class)->execute($this->school->id, $term->id);
        $this->toast(__('Waiting list positions recomputed.'));
    }

    public function render(): View
    {
        return view('boarding::allocation.waitlist', [
            'entries' => HostelWaitingListEntry::where('school_id', $this->school->id)
                ->where('status', 'waiting')
                ->with('student', 'preferredHostel')
                ->orderBy('position')
                ->get(),
            'hostels' => Hostel::where('school_id', $this->school->id)->orderBy('name')->get(),
            'students' => Student::where('school_id', $this->school->id)->whereIn('residency', ['BOARDER', 'WEEKLY_BOARDER'])->orderBy('first_name')->limit(200)->get(),
        ]);
    }
}
