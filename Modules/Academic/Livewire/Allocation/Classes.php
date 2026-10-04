<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Allocation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AllocateClassAction;
use Modules\Academic\Domain\DataObjects\AllocateClassData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * `Allocation\Classes` (Book D ACA-02 §5/BR-ACA-02-004/014,
 * `academic.allocation.manage`). A pick-and-submit form rather than
 * the spec's own "drag between streams" grid — the same simplification
 * `People\Allocation\TeacherMatrix` uses, and for the same reason: the
 * real work here is `AllocateClassAction`'s own supersede-and-
 * auto-enrol-compulsory logic, not a drag interaction. Live counts
 * read straight off `ClassAllocation` for the current term.
 */
#[Title('Class allocation')]
#[Layout('layouts.app')]
final class Classes extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $studentId = null;

    public ?int $classId = null;

    public string $allocationType = 'initial';

    public string $notes = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.allocation.manage');
    }

    public function allocate(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'classId' => ['required', 'integer'],
        ]);

        $termId = SessionContext::termId();

        if ($termId === null) {
            $this->addError('studentId', __('No active term is set for this school.'));

            return;
        }

        try {
            app(AllocateClassAction::class)->execute(new AllocateClassData(
                studentId: (int) $this->studentId,
                classId: (int) $this->classId,
                termId: $termId,
                allocatedByUserId: (int) Auth::id(),
                effectiveFrom: now(),
                allocationType: $this->allocationType,
                notes: $this->notes !== '' ? $this->notes : null,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'classId', 'notes']);
        $this->allocationType = 'initial';
        $this->toast(__('Student allocated to class.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::allocation.classes', [
            'students' => Student::where('school_id', $this->school->id)->whereNotIn('status', ['withdrawn', 'graduated', 'archived'])->orderBy('first_name')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'allocations' => $termId !== null
                ? ClassAllocation::where('term_id', $termId)->where('status', 'confirmed')->with('student', 'schoolClass')->orderByDesc('id')->limit(50)->get()
                : collect(),
        ]);
    }
}
