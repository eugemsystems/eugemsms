<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateCurriculumFrameworkAction;
use Modules\Academic\Domain\DataObjects\CreateCurriculumFrameworkData;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Curriculum\Frameworks` (Book D ACA-01 §5, `academic.curriculum.manage`
 * to create, `academic.curriculum.view` to list). List + create — no
 * `UpdateCurriculumFrameworkAction` exists, matching this pass's
 * established create-only precedent. Also carries the
 * `requires_confirmation` banner (BR-ACA-01-009): the spec's own
 * "mark reviewed for the current year" persistence has no column or
 * action backing it, so this surfaces the live list of unconfirmed
 * rules every time rather than a dismissable-once banner — a
 * deliberate simplification, not a missed requirement (see
 * `.ai/rules/academic.md`).
 */
#[Title('Curriculum frameworks')]
#[Layout('layouts.app')]
final class Frameworks extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $authority = '';

    public string $effectiveFrom = '';

    public string $continuousAssessmentModel = 'sbp';

    public string $referenceCircular = '';

    public string $notes = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');

        $this->effectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'authority' => ['required', 'string', 'max:80'],
            'effectiveFrom' => ['required', 'date'],
            'continuousAssessmentModel' => ['required', 'in:sbp,cala,none,coursework'],
        ]);

        app(CreateCurriculumFrameworkAction::class)->execute(new CreateCurriculumFrameworkData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            authority: $this->authority,
            effectiveFrom: Carbon::parse($this->effectiveFrom),
            continuousAssessmentModel: $this->continuousAssessmentModel,
            createdByUserId: (int) Auth::id(),
            referenceCircular: $this->referenceCircular !== '' ? $this->referenceCircular : null,
            notes: $this->notes !== '' ? $this->notes : null,
        ));

        $this->reset(['code', 'name', 'authority', 'referenceCircular', 'notes']);
        $this->toast(__('Framework created as draft.'));
    }

    public function render(): View
    {
        return view('academic::curriculum.frameworks', [
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
            'unconfirmedRules' => SubjectSelectionRule::where('school_id', $this->school->id)
                ->where('requires_confirmation', true)
                ->where('is_active', true)
                ->get(),
        ]);
    }
}
