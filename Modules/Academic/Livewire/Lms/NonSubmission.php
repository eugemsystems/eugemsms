<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Lms;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ChaseNonSubmittersAction;
use Modules\Academic\Domain\Actions\ListNonSubmittersAction;
use Modules\Academic\Domain\DataObjects\ChaseNonSubmittersData;
use Modules\Academic\Domain\DataObjects\ListNonSubmittersData;
use Modules\Academic\Livewire\Concerns\AuthorizesCourseSpace;
use Modules\Academic\Models\Assignment;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Academic\Lms\NonSubmission` (Book K ACA-08 §5, `lms.assignment.mark`).
 * The live list of learners who have not submitted after the due date, with
 * a one-tap reminder through CORE-09 (BR-ACA-08-009). The reminder only ever
 * reaches learners who genuinely have not submitted — the Action re-derives
 * that, whatever ids are sent.
 */
#[Title('Non-submission')]
#[Layout('layouts.app')]
final class NonSubmission extends Component
{
    use AuthorizesCourseSpace;
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public int $assignmentId;

    public function mount(School $school, int $assignment): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('lms.assignment.mark');

        $record = Assignment::query()->findOrFail($assignment);
        $this->authorizeSpace($record->course_space_id, 'lms.assignment.mark');
        $this->assignmentId = $record->id;
    }

    public function chase(): void
    {
        $this->authorizePermission('lms.assignment.mark');

        $assignment = Assignment::query()->findOrFail($this->assignmentId);
        $this->authorizeSpace($assignment->course_space_id, 'lms.assignment.mark');

        $students = app(ListNonSubmittersAction::class)->execute(new ListNonSubmittersData($assignment->id));
        $sent = app(ChaseNonSubmittersAction::class)->execute(new ChaseNonSubmittersData($assignment->id, $students->pluck('id')->all()));

        $this->toast(__(':sent reminder(s) sent.', ['sent' => $sent]));
    }

    public function render(): View
    {
        $assignment = Assignment::query()->findOrFail($this->assignmentId);

        return view('academic::lms.non-submission', [
            'assignment' => $assignment,
            'students' => app(ListNonSubmittersAction::class)->execute(new ListNonSubmittersData($assignment->id))->sortBy('last_name')->values(),
        ]);
    }
}
