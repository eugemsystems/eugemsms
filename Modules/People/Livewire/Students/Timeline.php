<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\RecordStudentTimelineEventAction;
use Modules\People\Models\Student;
use Modules\People\Models\StudentTimelineEvent;

/**
 * `People\Students\Timeline` (Book C PPL-01 §8, `people.students.view`). What has happened to a learner, newest first, filterable by category. Welfare detail is never here — only that something happened.
 */
#[Title('Learner timeline')]
#[Layout('layouts.app')]
final class Timeline extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.view');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public string $category = '';

    public function render(): View
    {
        return view('people::students.timeline', [
            'events' => StudentTimelineEvent::query()->where('student_id', $this->student->id)->when($this->category !== '', fn ($q) => $q->where('event_category', $this->category))->orderByDesc('occurred_at')->limit(200)->get(),
            'categories' => RecordStudentTimelineEventAction::CATEGORIES,
        ]);
    }
}
