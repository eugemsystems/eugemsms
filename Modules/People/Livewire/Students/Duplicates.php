<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\DetectPossibleDuplicatesAction;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;
use Modules\People\Models\Student;

/**
 * `People\Students\Duplicates` (Book C PPL-01 §8/BR-PPL-01-009,
 * `students.merge`). The spec names this a "review queue", but
 * `DetectPossibleDuplicatesAction` has no persisted queue behind it —
 * it is a stateless, in-the-moment check that only ever fires an
 * event (see `.ai/rules/people.md`). This screen is honestly scoped
 * to what's real: an on-demand scanner a registrar runs against a
 * name/date-of-birth/identifier, not a list of already-flagged
 * pending matches. `ACT-MergeDuplicateStudents` does not exist —
 * there is no merge action here, only the scan.
 */
#[Title('Duplicate learner scan')]
#[Layout('layouts.app')]
final class Duplicates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $firstName = '';

    public string $lastName = '';

    public string $dateOfBirth = '';

    public string $nationalRegistrationNo = '';

    public string $birthCertificateNo = '';

    public bool $hasScanned = false;

    /**
     * Plain arrays, not `DuplicateCandidate` objects — Livewire has no
     * synth for an arbitrary DTO class on a public property.
     *
     * @var array<int, array{studentId: int, admissionNumber: string, matchedOn: string}>
     */
    public array $results = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.merge');
    }

    public function scan(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'dateOfBirth' => ['required', 'date'],
        ]);

        $candidates = app(DetectPossibleDuplicatesAction::class)->execute(new DetectPossibleDuplicatesData(
            schoolId: $this->school->id,
            firstName: $this->firstName,
            lastName: $this->lastName,
            dateOfBirth: Carbon::parse($this->dateOfBirth),
            nationalRegistrationNo: $this->nationalRegistrationNo !== '' ? $this->nationalRegistrationNo : null,
            birthCertificateNo: $this->birthCertificateNo !== '' ? $this->birthCertificateNo : null,
        ));

        $this->results = array_map(fn ($candidate): array => [
            'studentId' => $candidate->studentId,
            'admissionNumber' => $candidate->admissionNumber,
            'matchedOn' => $candidate->matchedOn,
        ], $candidates);

        $this->hasScanned = true;
    }

    public function render(): View
    {
        return view('people::students.duplicates', [
            'students' => $this->matchedStudents(),
        ]);
    }

    /**
     * @return Collection<int, Student>
     */
    private function matchedStudents(): Collection
    {
        $ids = array_column($this->results, 'studentId');

        return Student::where('school_id', $this->school->id)->whereIn('id', $ids)->get()->keyBy('id');
    }
}
