<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\ConsentTypes;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\CreateConsentTypeAction;
use Modules\Compliance\Domain\DataObjects\CreateConsentTypeData;
use Modules\Compliance\Models\ConsentType;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\ConsentTypes` (Book H3 CMP-03 §4, `privacy.manage`).
 */
#[Title('Consent types')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $lawfulBasis = 'consent';

    public string $appliesTo = 'guardian';

    public bool $isWithdrawable = true;

    public bool $requiredForEnrolment = false;

    public string $renewalFrequencyMonths = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'lawfulBasis' => ['required', 'in:consent,contract,legal_obligation,vital_interest,legitimate_interest'],
            'appliesTo' => ['required', 'in:student,guardian,staff'],
        ]);

        app(CreateConsentTypeAction::class)->execute(new CreateConsentTypeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            description: $this->description,
            lawfulBasis: $this->lawfulBasis,
            appliesTo: $this->appliesTo,
            isWithdrawable: $this->isWithdrawable,
            requiredForEnrolment: $this->requiredForEnrolment,
            renewalFrequencyMonths: $this->renewalFrequencyMonths !== '' ? (int) $this->renewalFrequencyMonths : null,
        ));

        $this->reset(['code', 'name', 'description', 'renewalFrequencyMonths']);
        $this->toast(__('Consent type created.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.consent-types.index', [
            'consentTypes' => ConsentType::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
