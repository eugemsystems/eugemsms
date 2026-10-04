<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Assessment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateAssessmentTypeAction;
use Modules\Academic\Domain\DataObjects\CreateAssessmentTypeData;
use Modules\Academic\Models\AssessmentType;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Assessment\Types` (Book D ACA-05 §6, `academic.grading.manage` to
 * create, `.view` to list).
 */
#[Title('Assessment types')]
#[Layout('layouts.app')]
final class Types extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $category = 'coursework';

    public string $defaultWeightPercent = '';

    public bool $appearsOnReportCard = true;

    public bool $isExamination = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.grading.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.grading.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:coursework,examination,continuous_assessment'],
            'defaultWeightPercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        app(CreateAssessmentTypeAction::class)->execute(new CreateAssessmentTypeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            defaultWeightPercent: (float) $this->defaultWeightPercent,
            appearsOnReportCard: $this->appearsOnReportCard,
            isExamination: $this->isExamination,
        ));

        $this->reset(['code', 'name', 'defaultWeightPercent']);
        $this->toast(__('Assessment type created.'));
    }

    public function render(): View
    {
        return view('academic::assessment.types', [
            'types' => AssessmentType::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
