<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Admissions\Applications;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\SubmitApplicationAction;
use Modules\People\Domain\DataObjects\SubmitApplicationData;
use Modules\People\Models\Intake;

/**
 * `Admissions\Applications\Create` (Book C PPL-02 §5, `people.admissions.application_create`).
 * The spec's own public, unauthenticated form (BR-PPL-02-001) has no
 * controller built in this pass — this screen is the staff-facing
 * stand-in for capturing a walk-in or phoned-in application directly,
 * calling the same `SubmitApplicationAction` a public endpoint would.
 * Guardian rows skip `existing_guardian_id` matching here (the
 * registrar isn't expected to search it up front) — conversion still
 * links an existing guardian correctly by phone match regardless, per
 * `ConvertApplicationToStudentAction`'s own matching step.
 */
#[Title('New application')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $intakeId = null;

    public string $firstName = '';

    public string $middleNames = '';

    public string $lastName = '';

    public string $dateOfBirth = '';

    public string $gender = 'male';

    public string $nationality = 'ZW';

    public string $nationalRegistrationNo = '';

    public string $birthCertificateNo = '';

    public ?int $requestedGradeLevelId = null;

    public string $requestedEnrolmentType = 'FULL_TIME';

    public string $requestedResidency = 'DAY';

    public ?string $requestedPathway = null;

    /** @var array<int, int> */
    public array $requestedSubjectIds = [];

    public bool $hasSiblingAtSchool = false;

    public bool $guardianIsAlumnus = false;

    public bool $guardianIsStaff = false;

    /**
     * @var array<int, array{relationship: string, first_name: string, last_name: string, primary_phone: string, email: string, is_primary_contact: bool, is_fee_responsible: bool}>
     */
    public array $guardians = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.admissions.application_create');

        $this->addGuardian();
    }

    public function addGuardian(): void
    {
        $this->guardians[] = [
            'relationship' => 'mother', 'first_name' => '', 'last_name' => '', 'primary_phone' => '',
            'email' => '', 'is_primary_contact' => false, 'is_fee_responsible' => false,
        ];
    }

    public function removeGuardian(int $index): void
    {
        unset($this->guardians[$index]);
        $this->guardians = array_values($this->guardians);
    }

    public function save(): void
    {
        $this->validate([
            'intakeId' => ['required', 'integer'],
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'requestedGradeLevelId' => ['required', 'integer'],
            'requestedEnrolmentType' => ['required', 'in:FULL_TIME,PART_TIME'],
            'requestedResidency' => ['required', 'in:DAY,BOARDER,WEEKLY_BOARDER'],
            'guardians' => ['array', 'min:1'],
            'guardians.*.relationship' => ['required', 'string'],
        ]);

        $guardianPayload = array_values(array_filter(array_map(fn (array $g): array => [
            'relationship' => $g['relationship'],
            'first_name' => $g['first_name'] !== '' ? $g['first_name'] : null,
            'last_name' => $g['last_name'] !== '' ? $g['last_name'] : null,
            'primary_phone' => $g['primary_phone'] !== '' ? $g['primary_phone'] : null,
            'email' => $g['email'] !== '' ? $g['email'] : null,
            'is_primary_contact' => $g['is_primary_contact'],
            'is_fee_responsible' => $g['is_fee_responsible'],
        ], $this->guardians), fn (array $g): bool => $g['first_name'] !== null || $g['last_name'] !== null));

        $application = app(SubmitApplicationAction::class)->execute(new SubmitApplicationData(
            schoolId: $this->school->id,
            intakeId: (int) $this->intakeId,
            firstName: $this->firstName,
            lastName: $this->lastName,
            dateOfBirth: Carbon::parse($this->dateOfBirth),
            gender: $this->gender,
            requestedGradeLevelId: (int) $this->requestedGradeLevelId,
            requestedEnrolmentType: $this->requestedEnrolmentType,
            requestedResidency: $this->requestedResidency,
            guardians: $guardianPayload,
            createdByUserId: (int) Auth::id(),
            middleNames: $this->middleNames !== '' ? $this->middleNames : null,
            nationality: $this->nationality,
            nationalRegistrationNo: $this->nationalRegistrationNo !== '' ? $this->nationalRegistrationNo : null,
            birthCertificateNo: $this->birthCertificateNo !== '' ? $this->birthCertificateNo : null,
            requestedPathway: $this->requestedPathway,
            requestedSubjectIds: $this->requestedEnrolmentType === 'PART_TIME' && $this->requestedSubjectIds !== [] ? $this->requestedSubjectIds : null,
            hasSiblingAtSchool: $this->hasSiblingAtSchool,
            guardianIsAlumnus: $this->guardianIsAlumnus,
            guardianIsStaff: $this->guardianIsStaff,
        ));

        $this->redirectRoute('people.admissions.applications.show', ['school' => $this->school, 'application' => $application], navigate: true);
    }

    public function render(): View
    {
        return view('people::admissions.applications.create', [
            'intakes' => Intake::where('school_id', $this->school->id)->where('status', 'open')->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
