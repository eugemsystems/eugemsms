<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Supervision;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateLessonPlanAction;
use Modules\Academic\Domain\Actions\ReviewLessonPlanAction;
use Modules\Academic\Domain\Actions\SubmitLessonPlanAction;
use Modules\Academic\Domain\DataObjects\CreateLessonPlanData;
use Modules\Academic\Domain\DataObjects\ReviewLessonPlanData;
use Modules\Academic\Domain\DataObjects\SubmitLessonPlanData;
use Modules\Academic\Livewire\Concerns\ResolvesSupervisionReach;
use Modules\Academic\Models\LessonPlan;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Academic\Supervision\LessonPlans` (Book K ACA-11 §4). A teacher writes and
 * submits plans for their own lessons; a plan linked to a scheme of work is
 * refused until that scheme is approved (AC-ACA-11-001). Holders of
 * `supervision.scheme.approve` review submitted plans, never their own.
 */
#[Title('Lesson plans')]
#[Layout('layouts.app')]
final class LessonPlans extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesSupervisionReach;
    use Toasts;

    public string $lessonDate = '';

    public string $topic = '';

    public string $objectives = '';

    public string $activities = '';

    public string $resources = '';

    public string $differentiation = '';

    public ?int $schemeId = null;

    public ?int $reviewingId = null;

    public string $hodComments = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->holds('supervision.plan') || $this->holds('supervision.scheme.approve'), 403);
        $this->lessonDate = now()->toDateString();
    }

    public function create(): void
    {
        abort_unless($this->holds('supervision.plan'), 403);
        $this->resetErrorBag();

        $teacher = $this->ownStaff();

        if ($teacher === null) {
            $this->addError('topic', __('You need a staff record to write lesson plans.'));

            return;
        }

        $this->validate(['lessonDate' => ['required', 'date'], 'topic' => ['required', 'string', 'max:200']]);

        try {
            app(CreateLessonPlanAction::class)->execute(new CreateLessonPlanData(
                schoolId: $this->school->id, teacherStaffId: $teacher->id, lessonDate: Carbon::parse($this->lessonDate), topic: $this->topic,
                schemeOfWorkId: $this->schemeId, objectives: $this->nullable($this->objectives), activities: $this->nullable($this->activities),
                resourcesNeeded: $this->nullable($this->resources), differentiationNotes: $this->nullable($this->differentiation),
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('topic', $exception->getMessage());

            return;
        }

        $this->reset('topic', 'objectives', 'activities', 'resources', 'differentiation', 'schemeId');
        $this->toast(__('Lesson plan saved as a draft.'));
    }

    public function submit(int $planId): void
    {
        abort_unless($this->holds('supervision.plan'), 403);

        $plan = LessonPlan::query()->where('teacher_staff_id', $this->ownStaff()?->id)->find($planId);

        if ($plan === null) {
            $this->toast(__('That plan is not yours.'), 'danger');

            return;
        }

        try {
            app(SubmitLessonPlanAction::class)->execute(new SubmitLessonPlanData($plan->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Submitted.'));
    }

    public function startReview(int $planId): void
    {
        $this->authorizePermission('supervision.scheme.approve');
        $this->reviewingId = LessonPlan::query()->where('status', 'submitted')->whereKey($planId)->value('id');
        $this->hodComments = '';
    }

    public function review(): void
    {
        $this->authorizePermission('supervision.scheme.approve');

        $plan = $this->reviewingId === null ? null : LessonPlan::query()->find($this->reviewingId);

        if ($plan === null) {
            return;
        }

        try {
            app(ReviewLessonPlanAction::class)->execute(new ReviewLessonPlanData($plan->id, $this->nullable($this->hodComments), (int) auth()->id()));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->reset('reviewingId', 'hodComments');
        $this->toast(__('Reviewed.'));
    }

    private function nullable(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    public function render(): View
    {
        $own = $this->ownStaff();
        $canReview = $this->holds('supervision.scheme.approve');
        $queue = $canReview ? LessonPlan::query()->where('status', 'submitted')->orderBy('lesson_date')->limit(100)->get() : collect();

        return view('academic::supervision.lesson-plans', [
            'mine' => $own === null ? collect() : LessonPlan::query()->where('teacher_staff_id', $own->id)->orderByDesc('lesson_date')->limit(50)->get(),
            'queue' => $queue,
            'canReview' => $canReview,
            'canPlan' => $this->holds('supervision.plan') && $own !== null,
            'schemes' => $own === null ? collect() : SchemeOfWork::query()->where('teacher_staff_id', $own->id)->orderByDesc('id')->get(['id', 'subject_id', 'grade_level_id', 'status']),
            'teacherNames' => Staff::query()->whereIn('id', $queue->pluck('teacher_staff_id'))->get()->mapWithKeys(fn (Staff $s): array => [$s->id => $s->fullName()]),
        ]);
    }
}
