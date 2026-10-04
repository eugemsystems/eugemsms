<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateSyllabusAction;
use Modules\Academic\Domain\DataObjects\CreateSyllabusData;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Syllabus;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Curriculum\Syllabi` (Book D ACA-01 §5, `academic.curriculum.manage`
 * to create, `.view` to list). Text/metadata only — no file upload
 * wired here (`file_id` stays null); attaching the actual syllabus
 * document is deferred, matching this pass's "no new file-category
 * work beyond what a screen strictly needs" discipline.
 */
#[Title('Syllabus repository')]
#[Layout('layouts.app')]
final class Syllabi extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $subjectId = null;

    public ?int $frameworkId = null;

    public string $title = '';

    public string $version = '';

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
            'subjectId' => ['required', 'integer'],
            'frameworkId' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
        ]);

        app(CreateSyllabusAction::class)->execute(new CreateSyllabusData(
            schoolId: $this->school->id,
            subjectId: (int) $this->subjectId,
            frameworkId: (int) $this->frameworkId,
            title: $this->title,
            version: $this->version !== '' ? $this->version : null,
        ));

        $this->reset(['subjectId', 'title', 'version']);
        $this->toast(__('Syllabus added.'));
    }

    public function render(): View
    {
        return view('academic::curriculum.syllabi', [
            'syllabi' => Syllabus::where('school_id', $this->school->id)->with('subject')->orderByDesc('id')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
        ]);
    }
}
