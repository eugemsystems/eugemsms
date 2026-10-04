<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Domain\Actions\AllocateTeacherAction;
use Modules\People\Domain\Actions\EndTeacherAllocationAction;
use Modules\People\Domain\DataObjects\AllocateTeacherData;
use Modules\People\Domain\DataObjects\EndTeacherAllocationData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\TeacherAllocation;

/**
 * `People\Allocation\TeacherMatrix` (Book C PPL-04 §4 ⭐/BR-PPL-04-005/
 * 006/007, `people.staff.allocate`). A pick-and-submit form rather
 * than the spec's own "drag teachers into a grid" interaction — that
 * is a UX nicety, not a backend requirement, and this pass follows
 * `AllocateTeacherAction`'s own real validation (teaching-staff check,
 * one-class-teacher-per-class, workload ceiling) rather than
 * reimplementing any of it client-side. Live period counters read
 * straight off `StaffWorkload`, which `AllocateTeacherAction`/
 * `EndTeacherAllocationAction` both recalculate synchronously on every
 * change, so there is nothing to refresh manually.
 */
#[Title('Teacher allocation matrix')]
#[Layout('layouts.app')]
final class TeacherMatrix extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $staffId = null;

    public ?int $subjectId = null;

    public ?int $classId = null;

    public string $weeklyPeriods = '';

    public string $role = 'teacher';

    public bool $isClassTeacher = false;

    public bool $overrideCeiling = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.staff.allocate');
    }

    public function allocate(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'subjectId' => ['required', 'integer'],
            'classId' => ['required', 'integer'],
            'weeklyPeriods' => ['required', 'integer', 'min:1'],
        ]);

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            $this->addError('staffId', __('No active academic year/term is set for this school.'));

            return;
        }

        try {
            app(AllocateTeacherAction::class)->execute(new AllocateTeacherData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                staffId: (int) $this->staffId,
                subjectId: (int) $this->subjectId,
                classId: (int) $this->classId,
                weeklyPeriods: (int) $this->weeklyPeriods,
                allocatedByUserId: (int) Auth::id(),
                role: $this->role,
                isClassTeacher: $this->isClassTeacher,
                overrideCeiling: $this->overrideCeiling,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['staffId', 'subjectId', 'classId', 'weeklyPeriods', 'isClassTeacher', 'overrideCeiling']);
        $this->role = 'teacher';
        $this->toast(__('Teacher allocated.'));
    }

    public function endAllocation(int $allocationId): void
    {
        app(EndTeacherAllocationAction::class)->execute(new EndTeacherAllocationData(
            allocationId: $allocationId,
            endsOn: now(),
        ));

        $this->toast(__('Allocation ended.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('people::allocation.teacher-matrix', [
            'teachingStaff' => Staff::where('school_id', $this->school->id)->where('is_teaching', true)->orderBy('first_name')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->orderBy('name')->get(),
            'allocations' => $termId !== null
                ? TeacherAllocation::where('term_id', $termId)->where('status', 'active')->with('staff', 'subject', 'schoolClass')->get()
                : collect(),
            'workloads' => $termId !== null
                ? StaffWorkload::where('term_id', $termId)->with('staff')->orderByDesc('utilisation_percent')->get()
                : collect(),
        ]);
    }
}
