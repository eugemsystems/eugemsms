<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Projects;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateProjectRubricAction;
use Modules\Academic\Domain\DataObjects\CreateProjectRubricData;
use Modules\Academic\Domain\DataObjects\RubricCriterionInput;
use Modules\Academic\Models\ProjectRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Projects\Rubrics` (Book E ACA-06 §2/§6/§7/BR-ACA-06-007,
 * `academic.projects.manage`). List + create, criteria with a live
 * weight total mirroring `Grading\Scales`'s own band-contiguity
 * pattern — `CreateProjectRubricAction` itself refuses a total that
 * isn't 100%, so the live total here is a courtesy, not the only guard.
 */
#[Title('Project rubrics')]
#[Layout('layouts.app')]
final class Rubrics extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public ?int $subjectId = null;

    public string $totalMark = '100';

    /** @var array<int, array{criterion: string, maxMark: string, weightPercent: string, performanceLevels: string}> */
    public array $criteria = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.projects.view');

        $this->addCriterion();
    }

    public function addCriterion(): void
    {
        $this->criteria[] = ['criterion' => '', 'maxMark' => '', 'weightPercent' => '', 'performanceLevels' => ''];
    }

    public function removeCriterion(int $index): void
    {
        unset($this->criteria[$index]);
        $this->criteria = array_values($this->criteria);
    }

    public function weightTotal(): float
    {
        return array_sum(array_map(fn (array $c): float => $c['weightPercent'] !== '' ? (float) $c['weightPercent'] : 0.0, $this->criteria));
    }

    public function create(): void
    {
        $this->authorizePermission('academic.projects.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.criterion' => ['required', 'string'],
            'criteria.*.maxMark' => ['required', 'numeric', 'min:0'],
            'criteria.*.weightPercent' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            app(CreateProjectRubricAction::class)->execute(new CreateProjectRubricData(
                schoolId: $this->school->id,
                name: $this->name,
                criteria: array_map(fn (array $c): RubricCriterionInput => new RubricCriterionInput(
                    criterion: $c['criterion'],
                    maxMark: (float) $c['maxMark'],
                    weightPercent: (float) $c['weightPercent'],
                    performanceLevels: [],
                ), $this->criteria),
                totalMark: (float) $this->totalMark,
                subjectId: $this->subjectId,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['name', 'subjectId', 'criteria']);
        $this->addCriterion();
        $this->toast(__('Rubric created.'));
    }

    public function render(): View
    {
        return view('academic::projects.rubrics', [
            'rubrics' => ProjectRubric::where('school_id', $this->school->id)->with('criteria')->orderByDesc('id')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
