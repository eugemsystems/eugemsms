<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ApproveTermResultsAction;
use Modules\Academic\Domain\Actions\SetTermResultCommentsAction;
use Modules\Academic\Domain\DataObjects\ApproveTermResultsData;
use Modules\Academic\Domain\DataObjects\SetTermResultCommentsData;
use Modules\Academic\Livewire\Concerns\ChecksPermissions;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;

/**
 * `Results\Review` (Book D ACA-05 §6, `academic.result.review`). The grid of a
 * class's computed results with each learner's promotion recommendation, the
 * class and head comments (`academic.result.comment`), and the approval that
 * makes the results eligible for report card generation. A published result's
 * comments are locked.
 */
#[Title('Review results')]
#[Layout('layouts.app')]
final class Review extends Component
{
    use AuthorizesPermissions;
    use ChecksPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $classId = null;

    public ?int $editingId = null;

    public string $classTeacherComment = '';

    public string $headComment = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('academic.result.review');
    }

    public function updatedClassId(): void
    {
        $this->reset('editingId', 'classTeacherComment', 'headComment');
    }

    public function approve(): void
    {
        $this->authorizePermission('academic.result.review');
        $termId = SessionContext::termId();

        if ($this->classId === null || $termId === null) {
            $this->toast(__('Choose a class first.'), 'danger');

            return;
        }

        try {
            $count = app(ApproveTermResultsAction::class)->execute(new ApproveTermResultsData($termId, (int) auth()->id(), $this->classId));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->toast($exception->getMessage(), 'danger');

            return;
        }

        $this->toast(trans_choice(':count result approved.|:count results approved.', $count, ['count' => $count]));
    }

    public function edit(int $resultId): void
    {
        $this->authorizePermission('academic.result.comment');
        $result = TermResult::query()->where('class_id', $this->classId)->find($resultId);

        $this->editingId = $result?->id;
        $this->classTeacherComment = (string) $result?->class_teacher_comment;
        $this->headComment = (string) $result?->head_comment;
    }

    public function saveComments(): void
    {
        $this->authorizePermission('academic.result.comment');

        $result = $this->editingId === null ? null : TermResult::query()->where('class_id', $this->classId)->find($this->editingId);

        if ($result === null) {
            return;
        }

        try {
            app(SetTermResultCommentsAction::class)->execute(new SetTermResultCommentsData($result->id, $this->classTeacherComment, $this->headComment));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('classTeacherComment', $exception->getMessage());

            return;
        }

        $this->reset('editingId', 'classTeacherComment', 'headComment');
        $this->toast(__('Comments saved.'));
    }

    public function render(): View
    {
        $termId = SessionContext::termId();

        return view('academic::report-cards.review', [
            'classes' => SchoolClass::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'results' => $this->classId === null || $termId === null ? collect() : TermResult::query()->with('student')->where('term_id', $termId)->where('class_id', $this->classId)->orderBy('class_position')->get(),
            'canComment' => $this->holds('academic.result.comment'),
        ]);
    }
}
