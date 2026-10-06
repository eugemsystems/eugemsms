<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\RecordPriorSchoolAction;
use Modules\People\Domain\DataObjects\RecordPriorSchoolData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentPriorSchool;

/**
 * `People\Students\PriorHistory` (Book C PPL-01 §8, `people.students.view`; recording needs `people.students.document_manage`). Where the learner was before this school, and whether fees were left owing.
 */
#[Title('Prior schooling')]
#[Layout('layouts.app')]
final class PriorHistory extends Component
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

    public string $schoolName = '';

    public string $schoolType = '';

    public string $country = 'ZW';

    public string $attendedFrom = '';

    public string $attendedTo = '';

    public string $lastGrade = '';

    public string $reasonForLeaving = '';

    public bool $hadOutstandingFees = false;

    public function record(): void
    {
        $this->authorizePermission('people.students.document_manage');
        $this->resetErrorBag();
        $this->validate(['schoolName' => ['required', 'string', 'max:200'], 'attendedFrom' => ['nullable', 'date'], 'attendedTo' => ['nullable', 'date']]);

        try {
            app(RecordPriorSchoolAction::class)->execute(new RecordPriorSchoolData(
                schoolId: $this->school->id, studentId: $this->student->id, schoolName: $this->schoolName, schoolType: $this->schoolType === '' ? null : $this->schoolType,
                country: $this->country, attendedFrom: $this->attendedFrom === '' ? null : Carbon::parse($this->attendedFrom), attendedTo: $this->attendedTo === '' ? null : Carbon::parse($this->attendedTo),
                lastGradeCompleted: $this->lastGrade === '' ? null : $this->lastGrade, reasonForLeaving: $this->reasonForLeaving === '' ? null : $this->reasonForLeaving, hadOutstandingFees: $this->hadOutstandingFees,
            ));
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('schoolName', $exception->getMessage());

            return;
        }

        $this->reset('schoolName', 'schoolType', 'attendedFrom', 'attendedTo', 'lastGrade', 'reasonForLeaving', 'hadOutstandingFees');
        $this->toast(__('Prior school recorded.'));
    }

    public function render(): View
    {
        return view('people::students.prior-history', [
            'schools' => StudentPriorSchool::query()->where('student_id', $this->student->id)->orderByDesc('attended_to')->get(),
            'canRecord' => app(PermissionScopeResolver::class)->has(auth()->user(), 'people.students.document_manage', PermissionScope::Own),
        ]);
    }
}
