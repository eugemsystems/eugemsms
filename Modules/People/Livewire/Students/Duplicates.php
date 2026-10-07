<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\DetectPossibleDuplicatesAction;
use Modules\People\Domain\Actions\MergeDuplicateStudentsAction;
use Modules\People\Domain\DataObjects\DetectPossibleDuplicatesData;
use Modules\People\Domain\DataObjects\MergeStudentsData;
use Modules\People\Models\Student;

/**
 * `People\Students\Duplicates` (Book C PPL-01 §8/BR-PPL-01-009/010,
 * `people.students.merge` ⚠⚠). The spec names this a "review queue", but
 * `DetectPossibleDuplicatesAction` has no persisted queue behind it —
 * it is a stateless, in-the-moment check that only ever fires an
 * event (see `.ai/rules/people.md`). This screen is honestly scoped
 * to what's real: an on-demand scanner a registrar runs against a
 * name/date-of-birth/identifier — not a list of already-flagged pending
 * matches. Merging two of the scanned results runs
 * `MergeDuplicateStudentsAction` (same `merge(int $survivorId, int
 * $duplicateId)` shape as `Guardians\Duplicates`'s own merge control) —
 * see that Action's own docblock for exactly what is and isn't
 * reassigned, and why financial records are deliberately left alone.
 */
#[Title('Duplicate learner scan')]
#[Layout('layouts.app')]
final class Duplicates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $firstName = '';

    public string $lastName = '';

    public string $dateOfBirth = '';

    public string $nationalRegistrationNo = '';

    public string $birthCertificateNo = '';

    public string $mergeReason = '';

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

    public function merge(int $survivorId, int $duplicateId): void
    {
        $this->authorizePermission('people.students.merge');

        try {
            app(MergeDuplicateStudentsAction::class)->execute(new MergeStudentsData(
                survivingStudentId: Student::query()->where('school_id', $this->school->id)->findOrFail($survivorId)->id,
                mergedStudentId: Student::query()->where('school_id', $this->school->id)->findOrFail($duplicateId)->id,
                mergedByUserId: (int) auth()->id(),
                reason: trim($this->mergeReason) !== '' ? $this->mergeReason : null,
            ));
        } catch (ValidationException $e) {
            $this->toast(implode(' ', collect($e->errors())->flatten()->all()), 'danger');

            return;
        }

        $this->results = array_values(array_filter($this->results, fn (array $r): bool => $r['studentId'] !== $duplicateId));
        $this->toast(__('Merged. The duplicate record is kept, marked merged, and now points at the survivor.'));
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
