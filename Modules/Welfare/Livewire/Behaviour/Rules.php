<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Behaviour;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\CreateTriggerRuleAction;
use Modules\Welfare\Domain\Actions\EvaluateTriggerRulesAction;
use Modules\Welfare\Domain\DataObjects\CreateTriggerRuleData;
use Modules\Welfare\Models\BehaviourCategory;
use Modules\Welfare\Models\BehaviourTriggerRule;
use Modules\Welfare\Models\SanctionType;

/**
 * `Behaviour\Rules` (Book G BRD-07 §5, `behaviour.manage` ⚠). Trigger
 * rules suggest a sanction to a named human by default
 * (`is_automatic = false`) — `CreateTriggerRuleAction` itself refuses
 * to create an automatic rule unless the school has already,
 * explicitly, turned on `behaviour.automatic_sanctioning_enabled`
 * (BR-BRD-07-003), so this screen offers the checkbox but never
 * silently routes around that refusal.
 */
#[Title('Trigger rules')]
#[Layout('layouts.app')]
final class Rules extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $name = '';

    public string $triggerType = 'points_threshold';

    public ?int $suggestedSanctionId = null;

    public ?int $demeritThreshold = null;

    public ?int $windowDays = null;

    public ?int $categoryId = null;

    public ?int $repeatCount = null;

    public bool $isAutomatic = false;

    public ?int $evaluatingStudentId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.manage');
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string'],
            'triggerType' => ['required', 'string'],
            'suggestedSanctionId' => ['required', 'integer'],
        ]);

        try {
            app(CreateTriggerRuleAction::class)->execute(new CreateTriggerRuleData(
                schoolId: $this->school->id,
                name: $this->name,
                triggerType: $this->triggerType,
                suggestedSanctionId: (int) $this->suggestedSanctionId,
                demeritThreshold: $this->demeritThreshold,
                windowDays: $this->windowDays,
                categoryId: $this->categoryId,
                repeatCount: $this->repeatCount,
                isAutomatic: $this->isAutomatic,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['name', 'demeritThreshold', 'windowDays', 'categoryId', 'repeatCount', 'isAutomatic']);
        $this->toast(__('Trigger rule created.'));
    }

    public function evaluate(): void
    {
        if ($this->evaluatingStudentId === null) {
            $this->toast(__('Select a student to evaluate.'), 'danger');

            return;
        }

        $term = $this->school->currentAcademicYear()?->currentTerm();

        if ($term === null) {
            $this->toast(__('No current term is set.'), 'danger');

            return;
        }

        $evaluations = app(EvaluateTriggerRulesAction::class)->execute(
            $this->school->id,
            (int) $term->academic_year_id,
            $term->id,
            $this->evaluatingStudentId,
        );

        $this->toast(__(':count rule(s) triggered.', ['count' => $evaluations->count()]));
    }

    public function render(): View
    {
        return view('welfare::behaviour.rules', [
            'rules' => BehaviourTriggerRule::where('school_id', $this->school->id)->get(),
            'sanctionTypes' => SanctionType::where('school_id', $this->school->id)->orderBy('name')->get(),
            'categories' => BehaviourCategory::where('school_id', $this->school->id)->orderBy('name')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
        ]);
    }
}
