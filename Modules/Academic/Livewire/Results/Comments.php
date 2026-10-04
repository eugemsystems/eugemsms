<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Results;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateCommentBankEntryAction;
use Modules\Academic\Domain\DataObjects\CreateCommentBankEntryData;
use Modules\Academic\Models\CommentBank;
use Modules\Academic\Models\Subject;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Results\Comments` (Book D ACA-05 §6, `academic.result.comment` to
 * create, `.view` to list). Manages the suggestion bank only —
 * applying a bank entry onto a specific learner's `term_subject_results.
 * teacher_comment` has no Action in this pass (none exists to write
 * that column), so that half of the spec's own screen description is
 * deferred; see `.ai/rules/academic.md`.
 */
#[Title('Comment bank')]
#[Layout('layouts.app')]
final class Comments extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $scope = 'subject';

    public ?int $subjectId = null;

    public string $gradeBand = '';

    public string $text = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.result.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.result.comment');

        $this->validate([
            'scope' => ['required', 'in:subject,general,conduct'],
            'text' => ['required', 'string', 'max:500'],
        ]);

        app(CreateCommentBankEntryAction::class)->execute(new CreateCommentBankEntryData(
            schoolId: $this->school->id,
            scope: $this->scope,
            text: $this->text,
            createdByUserId: (int) Auth::id(),
            subjectId: $this->scope === 'subject' ? $this->subjectId : null,
            gradeBand: $this->gradeBand !== '' ? $this->gradeBand : null,
        ));

        $this->reset(['text', 'gradeBand']);
        $this->toast(__('Comment added to the bank.'));
    }

    public function render(): View
    {
        return view('academic::results.comments', [
            'comments' => CommentBank::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
