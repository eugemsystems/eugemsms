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
use Modules\Boarding\Domain\Actions\ConfirmBedAllocationAction;
use Modules\Boarding\Domain\Actions\RunBulkAllocationAction;
use Modules\Boarding\Domain\DataObjects\ConfirmBedAllocationData;
use Modules\Boarding\Domain\DataObjects\RunBulkAllocationData;
use Modules\Boarding\Models\BedAllocation;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Allocation\Run` (Book F BRD-01 §5 ⭐, `boarding.allocation.manage`).
 * `RunBulkAllocationAction` always produces drafts (BR-BRD-01-007) —
 * this screen is the human review step the spec requires before any
 * of them become real occupancy: every soft violation is listed, and
 * `ConfirmBedAllocationAction` is a separate, explicit, per-row call —
 * nothing here ever batch-confirms without a row-by-row click.
 */
#[Title('Bulk allocation run')]
#[Layout('layouts.app')]
final class Run extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, int> */
    public array $lastOutcomeStudentIds = [];

    /** @var array<int, array{studentId: int, placed: bool, blockingReason: ?string, softViolations: array<int,string>}> */
    public array $lastOutcomes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.allocation.view');
    }

    public function run(): void
    {
        $this->authorizePermission('boarding.allocation.manage');

        $year = $this->school->currentAcademicYear();
        $term = $year?->currentTerm();

        if ($year === null || $term === null) {
            $this->toast(__('No current academic year/term is set.'), 'danger');

            return;
        }

        $alreadyAllocatedStudentIds = BedAllocation::where('school_id', $this->school->id)
            ->whereIn('status', ['confirmed', 'draft'])
            ->whereNull('effective_to')
            ->pluck('student_id');

        $studentIds = Student::where('school_id', $this->school->id)
            ->whereIn('residency', ['BOARDER', 'WEEKLY_BOARDER'])
            ->whereNotIn('status', ['withdrawn', 'graduated', 'archived'])
            ->whereNotIn('id', $alreadyAllocatedStudentIds)
            ->pluck('id')
            ->all();

        if ($studentIds === []) {
            $this->toast(__('No unallocated boarders found.'), 'danger');

            return;
        }

        $outcomes = app(RunBulkAllocationAction::class)->execute(new RunBulkAllocationData(
            schoolId: $this->school->id,
            academicYearId: $year->id,
            termId: $term->id,
            studentIds: $studentIds,
            effectiveFrom: Carbon::now(),
            allocatedByUserId: (int) Auth::id(),
        ));

        $this->lastOutcomes = $outcomes->map(fn ($o) => [
            'studentId' => $o->studentId,
            'placed' => $o->isPlaced(),
            'blockingReason' => $o->blockingReason,
            'softViolations' => $o->softViolations,
        ])->all();

        $this->toast(__(':placed placed as drafts, :blocked blocked.', [
            'placed' => $outcomes->filter(fn ($o) => $o->isPlaced())->count(),
            'blocked' => $outcomes->reject(fn ($o) => $o->isPlaced())->count(),
        ]));
    }

    public function confirm(int $allocationId): void
    {
        $this->authorizePermission('boarding.allocation.manage');

        app(ConfirmBedAllocationAction::class)->execute(new ConfirmBedAllocationData(
            allocationId: $allocationId,
            confirmedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Allocation confirmed.'));
    }

    public function render(): View
    {
        $students = Student::whereIn('id', array_column($this->lastOutcomes, 'studentId'))->get()->keyBy('id');

        return view('boarding::allocation.run', [
            'students' => $students,
            'drafts' => BedAllocation::where('school_id', $this->school->id)->where('status', 'draft')->with('student', 'bed.room', 'hostel')->get(),
        ]);
    }
}
