<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\DetectPossibleDuplicatesAction;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;

/**
 * `People\Students\Create` (Book C PPL-01 §8/BR-PPL-01-009,
 * `students.create`). "Multi-step with duplicate check before save":
 * `check()` runs the real `DetectPossibleDuplicatesAction` and shows
 * any match before the registrar can proceed; `save()` then skips the
 * action's own internal check (already shown here) and creates for
 * real once confirmed.
 */
#[Title('New student')]
#[Layout('layouts.app')]
final class Create extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;

    public string $firstName = '';

    public string $middleNames = '';

    public string $lastName = '';

    public string $preferredName = '';

    public string $dateOfBirth = '';

    public string $gender = 'male';

    public string $nationality = 'ZW';

    public string $nationalRegistrationNo = '';

    public string $birthCertificateNo = '';

    public string $enrolmentType = 'FULL_TIME';

    public string $residency = 'DAY';

    public ?int $sectionId = null;

    public ?int $gradeLevelId = null;

    public ?string $pathway = null;

    public bool $hasCheckedDuplicates = false;

    /**
     * Plain arrays, not `DuplicateCandidate` objects — Livewire has no
     * synth for an arbitrary DTO class on a public property.
     *
     * @var array<int, array{studentId: int, admissionNumber: string, matchedOn: string}>
     */
    public array $duplicates = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.students.create');
    }

    public function check(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
        ]);

        $candidates = app(DetectPossibleDuplicatesAction::class)->execute(new DetectPossibleDuplicatesData(
            schoolId: $this->school->id,
            firstName: $this->firstName,
            lastName: $this->lastName,
            dateOfBirth: Carbon::parse($this->dateOfBirth),
            nationalRegistrationNo: $this->nationalRegistrationNo !== '' ? $this->nationalRegistrationNo : null,
            birthCertificateNo: $this->birthCertificateNo !== '' ? $this->birthCertificateNo : null,
        ));

        $this->duplicates = array_map(fn ($candidate): array => [
            'studentId' => $candidate->studentId,
            'admissionNumber' => $candidate->admissionNumber,
            'matchedOn' => $candidate->matchedOn,
        ], $candidates);

        $this->hasCheckedDuplicates = true;
    }

    public function save(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dateOfBirth' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'enrolmentType' => ['required', 'in:FULL_TIME,PART_TIME'],
            'residency' => ['required', 'in:DAY,BOARDER,WEEKLY_BOARDER'],
            'sectionId' => ['required', 'integer'],
            'gradeLevelId' => ['required', 'integer'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->addError('sectionId', __('No active academic year/term is set for this school.'));

            return;
        }

        try {
            $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
                schoolId: $this->school->id,
                academicYearId: $yearId,
                termId: $termId,
                firstName: $this->firstName,
                lastName: $this->lastName,
                dateOfBirth: Carbon::parse($this->dateOfBirth),
                gender: $this->gender,
                enrolmentType: $this->enrolmentType,
                residency: $this->residency,
                sectionId: (int) $this->sectionId,
                gradeLevelId: (int) $this->gradeLevelId,
                entryCohortYear: (int) now()->year,
                createdByUserId: (int) Auth::id(),
                middleNames: $this->middleNames !== '' ? $this->middleNames : null,
                preferredName: $this->preferredName !== '' ? $this->preferredName : null,
                nationality: $this->nationality,
                nationalRegistrationNo: $this->nationalRegistrationNo !== '' ? $this->nationalRegistrationNo : null,
                birthCertificateNo: $this->birthCertificateNo !== '' ? $this->birthCertificateNo : null,
                pathway: $this->pathway,
                skipDuplicateCheck: true,
            ));
        } catch (DomainException $e) {
            $this->addError('firstName', $e->getMessage());

            return;
        }

        $this->redirectRoute('people.students.show', ['school' => $this->school, 'student' => $student], navigate: true);
    }

    public function render(): View
    {
        return view('people::students.create', [
            'sections' => SchoolSection::where('school_id', $this->school->id)->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
        ]);
    }
}
