<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\UpdateStudentProfileAction;
use Modules\People\Domain\DataObjects\UpdateStudentProfileData;
use Modules\People\Models\Student;

/**
 * `People\Students\Edit` (Book C PPL-01 §8, `students.update`).
 * Non-billing attributes only — `enrolment_type`, `residency`,
 * `grade_level`, `class`, `section`, and `pathway` never appear here;
 * the model's own `booted()` guard throws if anything tries, so this
 * form physically cannot touch them.
 */
#[Title('Edit student')]
#[Layout('layouts.app')]
final class Edit extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Student $student;

    public string $firstName = '';

    public string $middleNames = '';

    public string $lastName = '';

    public string $preferredName = '';

    public string $homeLanguage = '';

    public string $religion = '';

    public string $addressLine1 = '';

    public string $addressLine2 = '';

    public string $suburb = '';

    public string $city = '';

    public string $province = '';

    public string $notes = '';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.update');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->firstName = $student->first_name;
        $this->middleNames = (string) $student->middle_names;
        $this->lastName = $student->last_name;
        $this->preferredName = (string) $student->preferred_name;
        $this->homeLanguage = (string) $student->home_language;
        $this->religion = (string) $student->religion;
        $this->addressLine1 = (string) $student->address_line_1;
        $this->addressLine2 = (string) $student->address_line_2;
        $this->suburb = (string) $student->suburb;
        $this->city = (string) $student->city;
        $this->province = (string) $student->province;
        $this->notes = (string) $student->notes;
    }

    public function save(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
        ]);

        app(UpdateStudentProfileAction::class)->execute(new UpdateStudentProfileData(
            studentId: $this->student->id,
            updatedByUserId: (int) Auth::id(),
            firstName: $this->firstName,
            middleNames: $this->middleNames !== '' ? $this->middleNames : null,
            lastName: $this->lastName,
            preferredName: $this->preferredName !== '' ? $this->preferredName : null,
            homeLanguage: $this->homeLanguage !== '' ? $this->homeLanguage : null,
            religion: $this->religion !== '' ? $this->religion : null,
            addressLine1: $this->addressLine1 !== '' ? $this->addressLine1 : null,
            addressLine2: $this->addressLine2 !== '' ? $this->addressLine2 : null,
            suburb: $this->suburb !== '' ? $this->suburb : null,
            city: $this->city !== '' ? $this->city : null,
            province: $this->province !== '' ? $this->province : null,
            notes: $this->notes !== '' ? $this->notes : null,
        ));

        $this->redirectRoute('people.students.show', ['school' => $this->school, 'student' => $this->student], navigate: true);
    }

    public function render(): View
    {
        return view('people::students.edit');
    }
}
