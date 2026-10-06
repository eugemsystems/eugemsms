<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Modules\People\Models\Student;

/**
 * Learner picker shared by the FIN-07 screens. The picked id is a
 * client-tamperable property, so every action re-resolves it through the
 * school-scoped `Student` model before using it.
 */
trait SearchesStudents
{
    public string $studentSearch = '';

    public ?int $selectedStudentId = null;

    public string $selectedStudentLabel = '';

    /**
     * @return Collection<int, Student>
     */
    protected function studentResults(): Collection
    {
        $term = trim($this->studentSearch);

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        return Student::query()
            ->where(fn ($q) => $q->where('admission_number', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
            ->orderBy('last_name')->limit(10)->get();
    }

    public function selectStudent(int $studentId): void
    {
        $student = Student::query()->findOrFail($studentId);

        $this->selectedStudentId = $student->id;
        $this->selectedStudentLabel = "{$student->admission_number} — {$student->fullName()}";
        $this->studentSearch = '';
    }

    protected function selectedStudent(): ?Student
    {
        return $this->selectedStudentId === null ? null : Student::query()->find($this->selectedStudentId);
    }
}
