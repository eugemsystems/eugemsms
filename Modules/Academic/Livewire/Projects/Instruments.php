<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateAssessmentInstrumentAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentInstrumentData;
use Modules\Academic\Models\AssessmentInstrument;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Instruments` (Book E ACA-06 §2/§3 ⭐/§7, `academic.curriculum.manage`
 * per the spec's own screen table — this screen reuses ACA-01's existing
 * curriculum permission rather than minting a new one, since an
 * assessment instrument is a property of a curriculum framework). List
 * + create — no `Update` action exists.
 */
#[Title('Assessment instruments')]
#[Layout('layouts.app')]
final class Instruments extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $frameworkId = null;

    public string $code = '';

    public string $name = '';

    public string $projectsPerSubjectPerYear = '1';

    public string $defaultWeightPercent = '';

    public bool $isReadonly = false;

    public string $referenceCircular = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'frameworkId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'projectsPerSubjectPerYear' => ['required', 'integer', 'min:1'],
        ]);

        app(CreateAssessmentInstrumentAction::class)->execute(new CreateAssessmentInstrumentData(
            schoolId: $this->school->id,
            frameworkId: $this->frameworkId,
            code: $this->code,
            name: $this->name,
            projectsPerSubjectPerYear: (int) $this->projectsPerSubjectPerYear,
            defaultWeightPercent: $this->defaultWeightPercent !== '' ? (float) $this->defaultWeightPercent : null,
            isReadonly: $this->isReadonly,
            referenceCircular: $this->referenceCircular !== '' ? $this->referenceCircular : null,
        ));

        $this->reset(['code', 'name', 'defaultWeightPercent', 'isReadonly', 'referenceCircular']);
        $this->toast(__('Assessment instrument created.'));
    }

    public function render(): View
    {
        return view('academic::projects.instruments', [
            'instruments' => AssessmentInstrument::where('school_id', $this->school->id)->with('framework')->orderByDesc('id')->get(),
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
