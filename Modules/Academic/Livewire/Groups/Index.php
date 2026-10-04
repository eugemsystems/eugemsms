<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Groups;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTeachingGroupAction;
use Modules\Academic\Domain\DataObjects\CreateTeachingGroupData;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Groups\Index` (Book D ACA-02 §6, `academic.group.manage` to create,
 * `.view` to list). Also stands in for the spec's separate "Subject
 * registers" screen — each group's own roster (via `Groups\Allocate`)
 * already shows who takes what, so a second printable register was
 * not built as a distinct screen.
 */
#[Title('Teaching groups')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $subjectId = null;

    public ?int $gradeLevelId = null;

    public string $code = '';

    public string $name = '';

    public string $setLevel = '';

    public string $capacity = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.group.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.group.manage');

        $this->validate([
            'subjectId' => ['required', 'integer'],
            'gradeLevelId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            $this->addError('subjectId', __('No active academic year/term is set for this school.'));

            return;
        }

        app(CreateTeachingGroupAction::class)->execute(new CreateTeachingGroupData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            subjectId: (int) $this->subjectId,
            gradeLevelId: (int) $this->gradeLevelId,
            code: $this->code,
            name: $this->name,
            setLevel: $this->setLevel !== '' ? $this->setLevel : null,
            teacherStaffId: null,
            roomId: null,
            capacity: $this->capacity !== '' ? (int) $this->capacity : null,
        ));

        $this->reset(['code', 'name', 'setLevel', 'capacity']);
        $this->toast(__('Teaching group created.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::groups.index', [
            'groups' => $termId !== null
                ? TeachingGroup::where('term_id', $termId)->with('subject', 'gradeLevel')->orderBy('code')->get()
                : collect(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
        ]);
    }
}
