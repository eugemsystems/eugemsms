<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateDiscussionThreadAction;
use Modules\Academic\Domain\Actions\HideDiscussionPostAction;
use Modules\Academic\Domain\Actions\PostDiscussionMessageAction;
use Modules\Academic\Domain\Actions\SetDiscussionThreadStateAction;
use Modules\Academic\Domain\DataObjects\CreateDiscussionThreadData;
use Modules\Academic\Domain\DataObjects\HideDiscussionPostData;
use Modules\Academic\Domain\DataObjects\PostDiscussionMessageData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\CourseSpace;
use Modules\Academic\Models\DiscussionPost;
use Modules\Academic\Models\DiscussionThread;
use Modules\Academic\Models\TeachingGroupMember;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;

/**
 * `Academic\Lms\Discussion` (Book K ACA-08 §5). A course space's threads.
 * Only the course teacher posts from this screen as staff (the Action
 * checks authorship against the space itself); moderators with
 * `lms.discussion.moderate` can lock or pin a thread and hide a post — never
 * delete it: a hidden post is kept for audit, invisible to learners, with
 * the moderator and reason recorded (BR-ACA-08-010).
 */
#[Title('Discussion')]
#[Layout('layouts.app')]
final class Discussion extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $spaceId;

    public ?int $threadId = null;

    public string $newTitle = '';

    public string $message = '';

    public ?int $hidingId = null;

    public string $hideReason = '';

    public function mount(School $school, int $space): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.course.manage');

        $this->spaceId = $this->authorizeSpace($space, 'lms.course.manage')->id;
    }

    public function openThread(int $threadId): void
    {
        $this->threadId = $this->thread($threadId)->id;
        $this->message = '';
    }

    public function createThread(): void
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.course.manage');
        $this->resetErrorBag();

        $this->validate(['newTitle' => ['required', 'string', 'max:200']]);

        try {
            $thread = app(CreateDiscussionThreadAction::class)->execute(new CreateDiscussionThreadData($space->id, $this->newTitle, (int) auth()->id()));
        } catch (InvalidArgumentException $exception) {
            $this->addError('newTitle', $exception->getMessage());

            return;
        }

        $this->newTitle = '';
        $this->threadId = $thread->id;
    }

    public function reply(): void
    {
        $this->authorizeSpace($this->spaceId, 'lms.course.manage');
        $this->resetErrorBag();

        abort_unless($this->threadId !== null, 422);
        $thread = $this->thread($this->threadId);
        $staffId = $this->currentStaffId();

        if ($staffId === null) {
            $this->addError('message', __('Only the course teacher can post from here.'));

            return;
        }

        $this->validate(['message' => ['required', 'string', 'max:5000']]);

        try {
            app(PostDiscussionMessageAction::class)->execute(new PostDiscussionMessageData($thread->id, 'staff', $staffId, $this->message));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('message', $exception->getMessage());

            return;
        }

        $this->message = '';
    }

    public function beginHide(int $postId): void
    {
        $this->authorizePermission('lms.discussion.moderate');

        $this->hidingId = $this->postRecord($postId)->id;
        $this->hideReason = '';
        $this->resetErrorBag();
    }

    public function hide(): void
    {
        $this->authorizePermission('lms.discussion.moderate');
        $this->resetErrorBag();

        abort_unless($this->hidingId !== null, 422);
        $post = $this->postRecord($this->hidingId);

        try {
            app(HideDiscussionPostAction::class)->execute(new HideDiscussionPostData($post->id, (int) auth()->id(), $this->hideReason));
        } catch (InvalidArgumentException $exception) {
            $this->addError('hideReason', $exception->getMessage());

            return;
        }

        $this->hidingId = null;
        $this->toast(__('Hidden from learners; kept for audit.'));
    }

    public function setState(int $threadId, string $state): void
    {
        $this->authorizePermission('lms.discussion.moderate');

        $thread = $this->thread($threadId);

        abort_unless(in_array($state, ['lock', 'unlock', 'pin', 'unpin'], true), 422);

        app(SetDiscussionThreadStateAction::class)->execute(
            $thread->id,
            isLocked: match ($state) {
                'lock' => true, 'unlock' => false, default => null
            },
            isPinned: match ($state) {
                'pin' => true, 'unpin' => false, default => null
            },
        );
    }

    private function thread(int $threadId): DiscussionThread
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.course.manage');

        return DiscussionThread::query()->where('course_space_id', $space->id)->findOrFail($threadId);
    }

    private function postRecord(int $postId): DiscussionPost
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.course.manage');

        return DiscussionPost::query()->whereIn('thread_id', DiscussionThread::query()->where('course_space_id', $space->id)->select('id'))->findOrFail($postId);
    }

    public function render(): View
    {
        $space = CourseSpace::query()->findOrFail($this->spaceId);
        $user = auth()->user();
        $posts = $this->threadId === null ? collect() : DiscussionPost::query()->where('thread_id', $this->threadId)->orderBy('posted_at')->limit(300)->get();
        $staffId = $this->currentStaffId();

        return view('academic::lms.discussion', [
            'space' => $space,
            'threads' => DiscussionThread::query()->where('course_space_id', $space->id)->orderByDesc('is_pinned')->orderByDesc('id')->get(),
            'current' => $this->threadId === null ? null : DiscussionThread::query()->where('course_space_id', $space->id)->find($this->threadId),
            'posts' => $posts,
            'staffNames' => Staff::query()->whereIn('id', $posts->where('posted_by_type', 'staff')->pluck('posted_by_id'))->get()->keyBy('id'),
            'studentNames' => Student::query()->whereIn('id', $posts->where('posted_by_type', 'student')->pluck('posted_by_id'))->get()->keyBy('id'),
            'canModerate' => $user !== null && app(PermissionScopeResolver::class)->has($user, 'lms.discussion.moderate', PermissionScope::Own),
            'isTeacher' => $staffId !== null && $staffId === $space->teacher_staff_id,
            'memberCount' => TeachingGroupMember::query()->where('teaching_group_id', $space->teaching_group_id)->count(),
        ]);
    }
}
