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
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CheckLearnerClearanceAction;
use Modules\People\Domain\Actions\TransferOutStudentAction;
use Modules\People\Domain\DataObjects\TransferOutStudentData;
use Modules\People\Domain\Exceptions\LearnerClearanceIncompleteException;
use Modules\People\Models\Student;

/**
 * `People\Students\TransferOut` (Book C PPL-01 §8, `people.students.transfer`). The clearance checklist inline, then the transfer. A failed clearance is listed per module; going ahead anyway needs a written reason.
 */
#[Title('Transfer out')]
#[Layout('layouts.app')]
final class TransferOut extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public string $exitedOn = '';

    public string $destination = '';

    public string $reason = '';

    public string $overrideReason = '';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.transfer');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->exitedOn = now()->toDateString();
    }

    public function transfer(): void
    {
        $this->authorizePermission('people.students.transfer');
        $this->resetErrorBag();
        $this->validate(['exitedOn' => ['required', 'date']]);

        try {
            app(TransferOutStudentAction::class)->execute(new TransferOutStudentData(
                studentId: $this->student->id, transferredByUserId: (int) auth()->id(), exitedOn: Carbon::parse($this->exitedOn),
                destinationSchool: $this->destination === '' ? null : $this->destination, reason: $this->reason === '' ? null : $this->reason,
                clearanceOverrideReason: $this->overrideReason === '' ? null : $this->overrideReason,
            ));
        } catch (LearnerClearanceIncompleteException $exception) {
            $this->addError('overrideReason', __('Clearance is incomplete. To transfer anyway, give a reason of at least 10 characters.'));

            return;
        } catch (DomainException|InvalidArgumentException $exception) {
            $this->addError('exitedOn', $exception->getMessage());

            return;
        }

        $this->toast(__('Transferred out.'));
        $this->redirectRoute('people.students.show', [$this->school, $this->student->fresh()], navigate: true);
    }

    public function render(): View
    {
        return view('people::students.transfer-out', ['clearance' => app(CheckLearnerClearanceAction::class)->execute($this->student->id)]);
    }
}
