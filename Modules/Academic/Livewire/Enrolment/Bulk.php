<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Enrolment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\BulkEnrolSubjectsAction;
use Modules\Academic\Domain\DataObjects\BulkEnrolSubjectsData;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Student;

/**
 * `Enrolment\Bulk` (Book D ACA-02 §6/BR-ACA-02-017, `academic.enrolment.manage`).
 * Pick a grade level (optionally narrowed to one class within it), tick
 * learners, and enrol them all in one subject in a single pass — the
 * same "tick learners, apply, report who moved and who was skipped"
 * shape `Allocation\Bulk` already uses for class/house allocation.
 * `BulkEnrolSubjectsAction` runs the real `EnrolSubjectAction` per
 * learner, so every rule-engine/proration/cutoff check a single
 * enrolment gets still applies — this is not a lighter validation
 * path. One shared "acknowledge warnings" checkbox covers the whole
 * batch rather than re-prompting per learner (see that action's own
 * docblock for why).
 */
#[Title('Bulk subject enrolment')]
#[Layout('layouts.app')]
final class Bulk extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $gradeLevelId = null;

    public ?int $classId = null;

    public bool $onlyNotEnrolled = true;

    /** @var array<int, int> */
    public array $selected = [];

    public ?int $subjectId = null;

    public string $effectiveFrom = '';

    public string $reason = '';

    public bool $acknowledgeWarnings = false;

    /** @var array<int, array{studentId: int, enrolled: bool, message: ?string}> */
    public array $outcomes = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.enrolment.manage');

        $this->effectiveFrom = now()->toDateString();
    }

    public function updatedGradeLevelId(): void
    {
        $this->classId = null;
        $this->selected = [];
        $this->outcomes = [];
    }

    public function updatedClassId(): void
    {
        $this->selected = [];
        $this->outcomes = [];
    }

    public function selectAll(): void
    {
        $this->selected = $this->learners()->pluck('id')->all();
    }

    public function enrol(): void
    {
        $this->resetErrorBag();

        $termId = SessionContext::termId();

        if ($termId === null || $this->subjectId === null || $this->selected === []) {
            $this->addError('subjectId', __('Choose a subject, make sure a term is current, and tick at least one learner.'));

            return;
        }

        $outcomes = app(BulkEnrolSubjectsAction::class)->execute(new BulkEnrolSubjectsData(
            studentIds: array_map('intval', $this->selected),
            subjectId: $this->subjectId,
            termId: $termId,
            addedByUserId: (int) Auth::id(),
            effectiveFrom: Carbon::parse($this->effectiveFrom),
            acknowledgeWarnings: $this->acknowledgeWarnings,
            reason: $this->reason !== '' ? $this->reason : null,
        ));

        $this->outcomes = $outcomes->map(fn ($o): array => ['studentId' => $o->studentId, 'enrolled' => $o->enrolled, 'message' => $o->message])->all();
        $this->selected = [];

        $enrolledCount = $outcomes->filter(fn ($o): bool => $o->enrolled)->count();
        $this->toast(__(':enrolled learner(s) enrolled; :failed not enrolled.', ['enrolled' => $enrolledCount, 'failed' => $outcomes->count() - $enrolledCount]));
    }

    /**
     * @return Collection<int, Student>
     */
    private function learners(): Collection
    {
        if ($this->gradeLevelId === null) {
            return collect();
        }

        $termId = SessionContext::termId();

        $alreadyEnrolled = $termId !== null && $this->subjectId !== null
            ? LearnerSubjectEnrolment::query()->where('term_id', $termId)->where('subject_id', $this->subjectId)->where('status', 'active')->pluck('student_id')
            : collect();

        $classStudentIds = $this->classId !== null && $termId !== null
            ? ClassAllocation::query()->where('class_id', $this->classId)->where('term_id', $termId)->where('status', 'confirmed')->pluck('student_id')
            : null;

        return Student::query()
            ->where('school_id', $this->school->id)
            ->where('grade_level_id', $this->gradeLevelId)
            ->whereNotIn('status', ['withdrawn', 'graduated', 'archived'])
            ->when($classStudentIds !== null, fn ($q) => $q->whereIn('id', $classStudentIds))
            ->when($this->onlyNotEnrolled, fn ($q) => $q->whereNotIn('id', $alreadyEnrolled))
            ->orderBy('last_name')->orderBy('first_name')->limit(300)->get();
    }

    public function render(): View
    {
        $learners = $this->learners();

        return view('academic::enrolment.bulk', [
            'gradeLevels' => GradeLevel::query()->where('school_id', $this->school->id)->orderBy('ordinal')->get(['id', 'name']),
            'classes' => SchoolClass::query()->where('school_id', $this->school->id)->where('is_active', true)->when($this->gradeLevelId !== null, fn ($q) => $q->where('grade_level_id', $this->gradeLevelId))->orderBy('name')->get(['id', 'name']),
            'subjects' => Subject::query()->where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
            'learners' => $learners,
            'names' => $learners->mapWithKeys(fn (Student $s): array => [$s->id => $s->last_name.', '.$s->first_name])->all(),
        ]);
    }
}
