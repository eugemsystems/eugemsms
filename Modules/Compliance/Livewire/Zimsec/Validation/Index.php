<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Zimsec\Validation;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CreateZimsecValidationRuleAction;
use Modules\Compliance\Domain\Actions\ValidateZimsecCandidatesAction;
use Modules\Compliance\Domain\DataObjects\CreateZimsecValidationRuleData;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Compliance\Models\ZimsecValidationRule;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Zimsec\Validation` (Book H3 CMP-01 §4 ⭐, `zimsec.manage`).
 * Candidate validation errors per field, fix-in-place by adjusting the
 * configurable rule set that produced them (`zimsec_validation_rules`
 * is data, not code, BR-CMP-01-003 — ZIMSEC's own field requirements
 * change between series) and re-running. Bio-data itself is never
 * edited here (BR-CMP-01-001) — a bad value is fixed at its source
 * (`Student`) and re-derived, not patched on the candidate row.
 */
#[Title('ZIMSEC candidate validation')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $registrationId = 0;

    public string $ruleField = 'national_registration_no';

    public string $ruleType = 'required';

    public string $ruleValue = '';

    public string $ruleSeverity = 'error';

    public string $ruleMessage = '';

    public string $ruleExamLevel = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('zimsec.manage');

        $this->registrationId = (int) (ZimsecRegistration::where('school_id', $school->id)->orderByDesc('id')->first()?->id);
    }

    public function validateCandidates(): void
    {
        $this->authorizePermission('zimsec.manage');

        if ($this->registrationId === 0) {
            $this->toast(__('Select a registration first.'), 'danger');

            return;
        }

        $results = app(ValidateZimsecCandidatesAction::class)->execute($this->registrationId);

        $this->toast(__('Validated :count candidate(s).', ['count' => $results->count()]));
    }

    public function createRule(): void
    {
        $this->authorizePermission('zimsec.manage');

        $this->validate([
            'ruleField' => ['required', 'string', 'max:60'],
            'ruleType' => ['required', 'in:required,format,min_count,max_count,allowed_values'],
            'ruleSeverity' => ['required', 'in:error,warning'],
            'ruleMessage' => ['required', 'string', 'max:255'],
        ]);

        app(CreateZimsecValidationRuleAction::class)->execute(new CreateZimsecValidationRuleData(
            field: $this->ruleField,
            ruleType: $this->ruleType,
            severity: $this->ruleSeverity,
            message: $this->ruleMessage,
            schoolId: $this->school->id,
            examLevel: $this->ruleExamLevel !== '' ? $this->ruleExamLevel : null,
            ruleValue: $this->ruleValue !== '' ? $this->ruleValue : null,
        ));

        $this->reset(['ruleValue', 'ruleMessage', 'ruleExamLevel']);
        $this->toast(__('Validation rule saved.'));
    }

    public function render(): View
    {
        $candidates = $this->registrationId !== 0
            ? ZimsecCandidate::where('registration_id', $this->registrationId)->orderBy('surname')->get()
            : collect();

        return view('compliance::zimsec.validation.index', [
            'registrations' => ZimsecRegistration::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'candidates' => $candidates,
            'rules' => ZimsecValidationRule::where(fn ($q) => $q->where('school_id', $this->school->id)->orWhereNull('school_id'))->orderByDesc('id')->get(),
        ]);
    }
}
