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
use Modules\Welfare\Domain\Actions\CreateBehaviourCategoryAction;
use Modules\Welfare\Domain\Actions\DeactivateBehaviourCategoryAction;
use Modules\Welfare\Domain\DataObjects\CreateBehaviourCategoryData;
use Modules\Welfare\Models\BehaviourCategory;

/**
 * `Behaviour\Categories` (Book G BRD-07 §5, `behaviour.manage` ⚠). The
 * seeded trigger-category set can never be fully emptied —
 * `DeactivateBehaviourCategoryAction` itself refuses to deactivate the
 * last active `is_safeguarding_trigger` category, surfaced here as a
 * plain toast, not a client-side guess at which one is "last".
 */
#[Title('Behaviour categories')]
#[Layout('layouts.app')]
final class Categories extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $polarity = 'positive';

    public int $defaultPoints = 1;

    public ?int $severityLevel = null;

    public bool $requiresEvidence = false;

    public bool $requiresHeadReview = false;

    public bool $autoNotifyGuardian = false;

    public bool $isSafeguardingTrigger = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('behaviour.manage');
    }

    public function create(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'polarity' => ['required', 'in:positive,negative'],
            'defaultPoints' => ['required', 'integer'],
        ]);

        app(CreateBehaviourCategoryAction::class)->execute(new CreateBehaviourCategoryData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            polarity: $this->polarity,
            defaultPoints: $this->defaultPoints,
            severityLevel: $this->severityLevel,
            requiresEvidence: $this->requiresEvidence,
            requiresHeadReview: $this->requiresHeadReview,
            autoNotifyGuardian: $this->autoNotifyGuardian,
            isSafeguardingTrigger: $this->isSafeguardingTrigger,
        ));

        $this->reset(['code', 'name', 'severityLevel', 'requiresEvidence', 'requiresHeadReview', 'autoNotifyGuardian', 'isSafeguardingTrigger']);
        $this->toast(__('Category created.'));
    }

    public function deactivate(int $categoryId): void
    {
        try {
            app(DeactivateBehaviourCategoryAction::class)->execute($categoryId);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Category deactivated.'));
    }

    public function render(): View
    {
        return view('welfare::behaviour.categories', [
            'categories' => BehaviourCategory::where('school_id', $this->school->id)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
