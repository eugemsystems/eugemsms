<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Guardians;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateGuardianAction;
use Modules\People\Domain\DataObjects\CreateGuardianData;

/**
 * `People\Guardians\Create` (Book C PPL-03 §8, `people.guardians.create`).
 * Create-only — no `UpdateGuardianAction` exists in the domain layer
 * (see `.ai/rules/people.md`), matching the same deliberate
 * create-only precedent already established for other screens in this
 * pass rather than fabricating an edit path with nothing behind it.
 */
#[Title('New guardian')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $guardianType = 'individual';

    public string $title = '';

    public string $firstName = '';

    public string $lastName = '';

    public string $organisationName = '';

    public string $organisationType = '';

    public string $primaryPhone = '';

    public string $email = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.guardians.create');
    }

    public function save(): void
    {
        $this->validate([
            'guardianType' => ['required', 'in:individual,organisation'],
            'firstName' => ['required_if:guardianType,individual', 'nullable', 'string', 'max:80'],
            'lastName' => ['required_if:guardianType,individual', 'nullable', 'string', 'max:80'],
            'organisationName' => ['required_if:guardianType,organisation', 'nullable', 'string', 'max:160'],
            'organisationType' => ['required_if:guardianType,organisation', 'nullable', 'string', 'max:40'],
            'primaryPhone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
        ]);

        $guardian = app(CreateGuardianAction::class)->execute(new CreateGuardianData(
            schoolId: $this->school->id,
            guardianType: $this->guardianType,
            createdByUserId: (int) Auth::id(),
            title: $this->title !== '' ? $this->title : null,
            firstName: $this->guardianType === 'individual' ? $this->firstName : null,
            lastName: $this->guardianType === 'individual' ? $this->lastName : null,
            organisationName: $this->guardianType === 'organisation' ? $this->organisationName : null,
            organisationType: $this->guardianType === 'organisation' ? $this->organisationType : null,
            primaryPhone: $this->primaryPhone !== '' ? $this->primaryPhone : null,
            email: $this->email !== '' ? $this->email : null,
        ));

        $this->redirectRoute('people.guardians.show', ['school' => $this->school, 'guardian' => $guardian], navigate: true);
    }

    public function render(): View
    {
        return view('people::guardians.create');
    }
}
