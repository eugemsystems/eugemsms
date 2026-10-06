<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\MarkAssignmentSubmissionAction;
use Modules\Academic\Domain\DataObjects\MarkAssignmentSubmissionData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\Assignment;
use Modules\Academic\Models\AssignmentSubmission;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Academic\Lms\Marking` (Book K ACA-08 §5, `lms.assignment.mark`).
 * Submissions with their similarity flags and inline feedback. A similarity
 * flag only asks the teacher to look — nothing is penalised automatically
 * (BR-ACA-08-006). The final mark is the raw mark less any late penalty,
 * computed once at marking time (BR-ACA-08-005); when the assignment feeds
 * the gradebook it is written through ACA-05's own mark entry
 * (BR-ACA-08-008). The submission id is re-checked against this assignment
 * on every call.
 */
#[Title('Marking')]
#[Layout('layouts.app')]
final class Marking extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $assignmentId;

    public ?int $markingId = null;

    public string $rawMark = '';

    public string $feedback = '';

    public function mount(School $school, int $assignment): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.assignment.mark');

        $record = Assignment::query()->findOrFail($assignment);
        $this->authorizeSpace($record->course_space_id, 'lms.assignment.mark');
        $this->assignmentId = $record->id;
    }

    public function begin(int $submissionId): void
    {
        $submission = $this->submission($submissionId);

        $this->markingId = $submission->id;
        $this->rawMark = $submission->raw_mark === null ? '' : (string) $submission->raw_mark;
        $this->feedback = (string) $submission->feedback;
        $this->resetErrorBag();
    }

    public function mark(): void
    {
        $this->authorizePermission('lms.assignment.mark');
        $this->resetErrorBag();

        abort_unless($this->markingId !== null, 422);
        $submission = $this->submission($this->markingId);

        $this->validate(['rawMark' => ['required', 'numeric', 'min:0'], 'feedback' => ['nullable', 'string', 'max:5000']]);

        try {
            app(MarkAssignmentSubmissionAction::class)->execute(new MarkAssignmentSubmissionData(
                submissionId: $submission->id, rawMark: (float) $this->rawMark, markedByUserId: (int) auth()->id(),
                feedback: $this->feedback === '' ? null : $this->feedback,
            ));
        } catch (InvalidArgumentException|DomainException $exception) {
            $this->addError('rawMark', $exception->getMessage());

            return;
        }

        $this->markingId = null;
        $this->toast(__('Marked.'));
    }

    private function submission(int $submissionId): AssignmentSubmission
    {
        $this->authorizePermission('lms.assignment.mark');

        $assignment = Assignment::query()->findOrFail($this->assignmentId);
        $this->authorizeSpace($assignment->course_space_id, 'lms.assignment.mark');

        return AssignmentSubmission::query()->where('assignment_id', $assignment->id)->findOrFail($submissionId);
    }

    public function render(): View
    {
        $assignment = Assignment::query()->findOrFail($this->assignmentId);
        $submissions = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->orderBy('student_id')->orderByDesc('attempt_number')->get();

        return view('academic::lms.marking', [
            'assignment' => $assignment,
            'submissions' => $submissions,
            'students' => Student::query()->whereIn('id', $submissions->pluck('student_id'))->get()->keyBy('id'),
        ]);
    }
}
