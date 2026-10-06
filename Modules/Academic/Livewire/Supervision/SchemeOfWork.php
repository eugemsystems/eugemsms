<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ApproveSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\CreateSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\ReturnSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\SubmitSchemeOfWorkAction;
use Modules\Academic\Domain\Actions\UpdateSchemeOfWorkAction;
use Modules\Academic\Domain\DataObjects\ApproveSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\CreateSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\ReturnSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\SubmitSchemeOfWorkData;
use Modules\Academic\Domain\DataObjects\UpdateSchemeOfWorkData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\SchemeOfWork as Scheme;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\SchemeOfWork` (Book K ACA-11 §4). A teacher drafts a
 * scheme per subject, grade level and term, submits it, and revises it if the
 * HOD returns it. Holders of `supervision.scheme.approve` decide submitted
 * schemes — never their own (BR-ACA-11-001). The teacher is always the signed-
 * in user's own staff record; nothing the browser sends can name another.
 */
#[Title('Schemes of work')]
#[Layout('layouts.app')]
final class SchemeOfWork extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public ?int $subjectId = null;

    public ?int $gradeLevelId = null;

    public string $topicsText = '';

    public ?int $editingId = null;

    public ?int $returningId = null;

    public string $returnComment = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.plan') || $this->holds('supervision.scheme.approve'), 403);
    }

    public function save(): void
    {
        abort_unless($this->holds('supervision.plan'), 403);
        $this->resetErrorBag();

        $teacher = $this->ownStaff();
        $term = Term::query()->find($this->currentTermId());

        if ($teacher === null || $term === null || $this->subjectId === null || $this->gradeLevelId === null) {
            $this->addError('topicsText', __('Choose a subject and grade level (you also need a staff record and a current term).'));

            return;
        }

        try {
            $topics = $this->parseTopics();

            if ($this->editingId !== null) {
                $scheme = Scheme::query()->where('teacher_staff_id', $teacher->id)->findOrFail($this->editingId);
                app(UpdateSchemeOfWorkAction::class)->execute(new UpdateSchemeOfWorkData($scheme->id, $topics));
            } else {
                app(CreateSchemeOfWorkAction::class)->execute(new CreateSchemeOfWorkData(
                    schoolId: $this->school->id, academicYearId: $term->academic_year_id, termId: $term->id,
                    subjectId: $this->subjectId, gradeLevelId: $this->gradeLevelId, teacherStaffId: $teacher->id, plannedTopics: $topics,
                ));
            }
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('topicsText', $exception->getMessage());

            return;
        }

        $this->reset('subjectId', 'gradeLevelId', 'topicsText', 'editingId');
        $this->toast(__('Scheme saved.'));
    }

    public function edit(int $schemeId): void
    {
        abort_unless($this->holds('supervision.plan'), 403);

        $scheme = Scheme::query()->where('teacher_staff_id', $this->ownStaff()?->id)->whereIn('status', ['draft', 'returned'])->findOrFail($schemeId);

        $this->editingId = $scheme->id;
        $this->subjectId = $scheme->subject_id;
        $this->gradeLevelId = $scheme->grade_level_id;
        $this->topicsText = collect($scheme->planned_topics)
            ->map(fn (array $t): string => implode(' | ', [$t['week'] ?? '', $t['topic'] ?? '', $t['objectives'] ?? '', $t['resources'] ?? '']))->implode("\n");
    }

    public function submit(int $schemeId): void
    {
        abort_unless($this->holds('supervision.plan'), 403);

        $scheme = Scheme::query()->where('teacher_staff_id', $this->ownStaff()?->id)->find($schemeId);

        if ($scheme === null) {
            $this->toast(__('That scheme is not yours.'), 'danger');

            return;
        }

        $this->run(fn () => app(SubmitSchemeOfWorkAction::class)->execute(new SubmitSchemeOfWorkData($scheme->id)), __('Submitted for approval.'));
    }

    public function approve(int $schemeId): void
    {
        $this->authorizePermission('supervision.scheme.approve');

        $scheme = Scheme::query()->find($schemeId);

        if ($scheme === null) {
            return;
        }

        $this->run(fn () => app(ApproveSchemeOfWorkAction::class)->execute(new ApproveSchemeOfWorkData($scheme->id, (int) auth()->id())), __('Scheme approved.'));
    }

    public function startReturn(int $schemeId): void
    {
        $this->authorizePermission('supervision.scheme.approve');
        $this->returningId = Scheme::query()->where('status', 'submitted')->whereKey($schemeId)->value('id');
        $this->returnComment = '';
    }

    public function sendBack(): void
    {
        $this->authorizePermission('supervision.scheme.approve');

        $scheme = $this->returningId === null ? null : Scheme::query()->find($this->returningId);

        if ($scheme === null) {
            return;
        }

        if ($this->run(fn () => app(ReturnSchemeOfWorkAction::class)->execute(new ReturnSchemeOfWorkData($scheme->id, (int) auth()->id(), $this->returnComment)), __('Returned to the teacher.'))) {
            $this->reset('returningId', 'returnComment');
        }
    }

    private function run(callable $callback, string $success): bool
    {
        try {
            $callback();
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return false;
        }

        $this->toast($success);

        return true;
    }

    /**
     * One topic per line: week | topic | objectives | resources.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseTopics(): array
    {
        $topics = [];

        foreach (preg_split('/\R/', $this->topicsText) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            $topics[] = ['week' => $parts[0], 'topic' => $parts[1] ?? '', 'objectives' => $parts[2] ?? '', 'resources' => $parts[3] ?? ''];
        }

        return $topics;
    }

    public function render(): View
    {
        $own = $this->ownStaff();
        $canApprove = $this->holds('supervision.scheme.approve');

        $mine = $own === null ? collect() : Scheme::query()->where('teacher_staff_id', $own->id)->orderByDesc('id')->limit(50)->get();
        $queue = $canApprove ? Scheme::query()->where('status', 'submitted')->orderBy('id')->limit(100)->get() : collect();

        return view('academic::supervision.schemes', [
            'mine' => $mine,
            'queue' => $queue,
            'canApprove' => $canApprove,
            'canPlan' => $this->holds('supervision.plan') && $own !== null,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => GradeLevel::query()->orderBy('ordinal')->get(['id', 'name']),
            'subjectNames' => Subject::query()->pluck('name', 'id'),
            'gradeNames' => GradeLevel::query()->pluck('name', 'id'),
            'teacherNames' => Staff::query()->whereIn('id', $queue->pluck('teacher_staff_id'))->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]),
        ]);
    }
}
