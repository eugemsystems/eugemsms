<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateSubjectAction;
use Modules\Academic\Domain\DataObjects\CreateSubjectData;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\SubjectGroup;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Curriculum\Subjects` (Book D ACA-01 §5, `academic.curriculum.manage`
 * to create, `.view` to list). Folds the spec's separate "Subject
 * catalogue" and "Subject editor" screens into one list + create —
 * create-only, since no `UpdateSubjectAction` exists (BR-ACA-01-005's
 * deactivation guard is left for a future amendment action).
 */
#[Title('Subjects')]
#[Layout('layouts.app')]
final class Subjects extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $frameworkId = null;

    public ?int $subjectGroupId = null;

    public string $code = '';

    public string $name = '';

    public string $shortName = '';

    public string $subjectType = 'core';

    public string $zimsecSubjectCode = '';

    public bool $isExaminable = true;

    public bool $requiresSbp = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');

        $this->frameworkId = CurriculumFramework::where('school_id', $school->id)->orderByDesc('effective_from')->value('id');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'frameworkId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'shortName' => ['required', 'string', 'max:40'],
            'subjectType' => ['required', 'in:core,elective,practical,vocational,co_curricular'],
        ]);

        app(CreateSubjectAction::class)->execute(new CreateSubjectData(
            schoolId: $this->school->id,
            frameworkId: (int) $this->frameworkId,
            code: $this->code,
            name: $this->name,
            shortName: $this->shortName,
            subjectType: $this->subjectType,
            createdByUserId: (int) Auth::id(),
            subjectGroupId: $this->subjectGroupId,
            zimsecSubjectCode: $this->zimsecSubjectCode !== '' ? $this->zimsecSubjectCode : null,
            isExaminable: $this->isExaminable,
            requiresSbp: $this->requiresSbp,
        ));

        $this->reset(['code', 'name', 'shortName', 'zimsecSubjectCode', 'subjectGroupId']);
        $this->toast(__('Subject created.'));
    }

    public function render(): View
    {
        return view('academic::curriculum.subjects', [
            'subjects' => Subject::where('school_id', $this->school->id)->with('framework', 'subjectGroup')->orderBy('name')->get(),
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
            'groups' => SubjectGroup::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
