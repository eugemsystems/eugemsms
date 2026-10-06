<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CloseAssignmentAction;
use Modules\Academic\Domain\Actions\CreateContentItemAction;
use Modules\Academic\Domain\Actions\PublishAssignmentAction;
use Modules\Academic\Domain\Actions\PublishContentItemAction;
use Modules\Academic\Domain\DataObjects\CreateContentItemData;
use Modules\Academic\Domain\DataObjects\PublishAssignmentData;
use Modules\Academic\Domain\DataObjects\PublishContentItemData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\ContentItem;
use Modules\Academic\Models\CourseSpace as CourseSpaceModel;
use Modules\Academic\Models\DiscussionThread;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TeachingGroup;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\School;

/**
 * `Academic\Lms\CourseSpace` (Book K ACA-08 §5). The teacher's view of one
 * course space: content with its size shown BEFORE anyone downloads it, and
 * stream-only items flagged so large video never queues into a phone's
 * offline cache (BR-ACA-08-003, AC-ACA-08-001); and the assignments, with
 * publish and close. Files come from the school's file vault (already
 * virus-scanned by CORE-10 — a file that has not passed cannot be
 * published); a link must be http(s).
 */
#[Title('Course space')]
#[Layout('layouts.app')]
final class CourseSpace extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $spaceId;

    public string $contentType = 'note';

    public string $title = '';

    public string $externalUrl = '';

    public ?int $fileId = null;

    public bool $offline = true;

    public function mount(School $school, int $space): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.course.manage');

        $this->spaceId = $this->authorizeSpace($space, 'lms.course.manage')->id;
    }

    public function addContent(): void
    {
        $this->authorizePermission('lms.course.manage');
        $this->resetErrorBag();

        $space = $this->authorizeSpace($this->spaceId, 'lms.course.manage');

        $this->validate(['title' => ['required', 'string', 'max:200'], 'externalUrl' => ['nullable', 'string', 'max:500']]);

        try {
            app(CreateContentItemAction::class)->execute(new CreateContentItemData(
                courseSpaceId: $space->id, contentType: $this->contentType, title: $this->title,
                fileId: $this->fileId, externalUrl: $this->externalUrl === '' ? null : $this->externalUrl, isDownloadableOffline: $this->offline,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'externalUrl', 'fileId');
        $this->toast(__('Added as a draft — publish it when it is ready.'));
    }

    public function publishContent(int $itemId): void
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.course.manage');

        $item = ContentItem::query()->where('course_space_id', $space->id)->findOrFail($itemId);

        try {
            app(PublishContentItemAction::class)->execute(new PublishContentItemData($item->id));
        } catch (InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Published to learners.'));
    }

    public function publishAssignment(int $assignmentId): void
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.assignment.create');

        $assignment = Assignment::query()->where('course_space_id', $space->id)->findOrFail($assignmentId);

        try {
            app(PublishAssignmentAction::class)->execute(new PublishAssignmentData($assignment->id));
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Assignment published.'));
    }

    public function closeAssignment(int $assignmentId): void
    {
        $space = $this->authorizeSpace($this->spaceId, 'lms.assignment.create');

        $assignment = Assignment::query()->where('course_space_id', $space->id)->findOrFail($assignmentId);

        try {
            app(CloseAssignmentAction::class)->execute($assignment->id);
        } catch (DomainException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Assignment closed to new submissions.'));
    }

    public function render(): View
    {
        $space = CourseSpaceModel::query()->findOrFail($this->spaceId);
        $items = ContentItem::query()->where('course_space_id', $space->id)->orderBy('sort_order')->orderBy('id')->get();

        return view('academic::lms.course-space', [
            'space' => $space,
            'group' => TeachingGroup::query()->find($space->teaching_group_id),
            'subject' => Subject::query()->find($space->subject_id),
            'items' => $items,
            'assignments' => Assignment::query()->where('course_space_id', $space->id)->orderByDesc('due_at')->get(),
            'threadCount' => DiscussionThread::query()->where('course_space_id', $space->id)->count(),
            'files' => File::query()->where('scan_status', '!=', 'infected')->orderByDesc('id')->limit(100)->get(['id', 'original_name', 'size_bytes', 'scan_status']),
        ]);
    }
}
