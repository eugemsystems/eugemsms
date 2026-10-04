<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ChangeStudentStatusAction;
use Modules\People\Domain\Actions\ReadmitStudentAction;
use Modules\People\Domain\Actions\WithdrawStudentAction;
use Modules\People\Domain\DataObjects\ChangeStudentStatusData;
use Modules\People\Domain\DataObjects\ReadmitStudentData;
use Modules\People\Domain\DataObjects\WithdrawStudentData;
use Modules\People\Domain\Support\StudentStatusMachine;
use Modules\People\Models\Student;

/**
 * `People\Students\ChangeStatus` (Book C PPL-01 §6/§8/BR-PPL-01-011,
 * `students.change_status`). Routes to the more specific
 * `WithdrawStudentAction`/`ReadmitStudentAction` where the target
 * transition is exactly what they exist for — both still go through
 * `ChangeStudentStatusAction` underneath, so the state machine guards
 * every path the same way. Suspension deliberately does not stop
 * billing (BR-PPL-01-012) — nothing on this screen implies it does.
 */
#[Title('Change student status')]
#[Layout('layouts.app')]
final class ChangeStatus extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public Student $student;

    public string $newStatus = '';

    public string $reasonCode = '';

    public string $reason = '';

    public string $exitedOn = '';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('people.students.change_status');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->exitedOn = now()->toDateString();
    }

    public function save(): void
    {
        $this->validate(['newStatus' => ['required', 'string']]);

        if (! StudentStatusMachine::canTransition($this->student->status, $this->newStatus)) {
            $this->addError('newStatus', __('That transition is not allowed from the current status.'));

            return;
        }

        try {
            if ($this->newStatus === 'withdrawn') {
                $this->validate(['exitedOn' => ['required', 'date']]);

                app(WithdrawStudentAction::class)->execute(new WithdrawStudentData(
                    studentId: $this->student->id,
                    exitedOn: Carbon::parse($this->exitedOn),
                    withdrawnByUserId: (int) Auth::id(),
                    reason: $this->reason !== '' ? $this->reason : null,
                ));
            } elseif ($this->student->status === 'withdrawn' && $this->newStatus === 'active') {
                $yearId = SessionContext::yearId();
                $termId = SessionContext::termId();

                if ($yearId === null || $termId === null) {
                    $this->addError('newStatus', __('No active academic year/term is set for this school.'));

                    return;
                }

                app(ReadmitStudentAction::class)->execute(new ReadmitStudentData(
                    studentId: $this->student->id, academicYearId: $yearId, termId: $termId, readmittedByUserId: (int) Auth::id(),
                ));
            } else {
                app(ChangeStudentStatusAction::class)->execute(new ChangeStudentStatusData(
                    studentId: $this->student->id,
                    newStatus: $this->newStatus,
                    changedByUserId: (int) Auth::id(),
                    reasonCode: $this->reasonCode !== '' ? $this->reasonCode : null,
                    reason: $this->reason !== '' ? $this->reason : null,
                ));
            }
        } catch (DomainException $e) {
            $this->addError('newStatus', $e->getMessage());

            return;
        }

        $this->student = $this->student->fresh();
        $this->newStatus = '';
        $this->toast(__('Status updated.'));
    }

    public function render(): View
    {
        return view('people::students.change-status', [
            'allowedStatuses' => StudentStatusMachine::allowedFrom($this->student->status),
        ]);
    }
}
