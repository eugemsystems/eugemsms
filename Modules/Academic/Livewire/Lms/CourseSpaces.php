<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateCourseSpaceAction;
use Modules\Academic\Domain\DataObjects\CreateCourseSpaceData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Lms\CourseSpaces` (Book K ACA-08 §5, `lms.course.manage`). One
 * course space per teaching group per term, mirrored 1:1 from ACA-02
 * (BR-ACA-08-001) — there is no separate LMS class list. A teacher sees and
 * creates spaces only for the groups they teach; school-reach holders see
 * everything.
 */
#[Title('Course spaces')]
#[Layout('layouts.app')]
final class CourseSpaces extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $teachingGroupId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.course.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('lms.course.manage');
        $this->resetErrorBag();

        $this->validate(['teachingGroupId' => ['required', 'integer']]);

        $group = TeachingGroup::query()->findOrFail($this->teachingGroupId);

        if (! $this->holdsSchoolReach()) {
            abort_unless($group->teacher_staff_id !== null && $group->teacher_staff_id === $this->currentStaffId(), 403);
        }

        try {
            app(CreateCourseSpaceAction::class)->execute(new CreateCourseSpaceData($group->id));
        } catch (DomainException $exception) {
            $this->addError('teachingGroupId', $exception->getMessage());

            return;
        }

        $this->reset('teachingGroupId');
        $this->toast(__('Course space created.'));
    }

    private function holdsSchoolReach(): bool
    {
        $user = auth()->user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, 'lms.course.manage', PermissionScope::School);
    }

    public function render(): View
    {
        $mine = $this->holdsSchoolReach() ? null : $this->currentStaffId();

        $spaces = CourseSpace::query()
            ->when($mine !== null, fn ($q) => $q->where('teacher_staff_id', $mine))
            ->when($mine === null && ! $this->holdsSchoolReach(), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderByDesc('id')->limit(200)->get();

        $groups = TeachingGroup::query()
            ->whereNotIn('id', CourseSpace::query()->select('teaching_group_id'))
            ->when($mine !== null, fn ($q) => $q->where('teacher_staff_id', $mine))
            ->when($mine === null && ! $this->holdsSchoolReach(), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('name')->limit(300)->get(['id', 'name', 'code']);

        return view('academic::lms.course-spaces', [
            'spaces' => $spaces,
            'groupNames' => TeachingGroup::query()->whereIn('id', $spaces->pluck('teaching_group_id'))->pluck('name', 'id'),
            'subjectNames' => Subject::query()->whereIn('id', $spaces->pluck('subject_id'))->pluck('name', 'id'),
            'groups' => $groups,
        ]);
    }
}
