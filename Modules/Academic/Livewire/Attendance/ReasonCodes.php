<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Attendance;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateAttendanceReasonCodeAction;
use Modules\Academic\Domain\DataObjects\CreateAttendanceReasonCodeData;
use Modules\Academic\Models\AttendanceReasonCode;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Attendance\ReasonCodes` (Book D ACA-04 §2, `academic.attendance.manage`
 * to create, `.view` to list). Not its own spec-named screen, but the
 * configuration every other attendance screen depends on
 * (`counts_as_present`/`suppresses_notification`/`triggers_welfare_flag`)
 * — built here since no screen existed anywhere to manage it.
 */
#[Title('Attendance reason codes')]
#[Layout('layouts.app')]
final class ReasonCodes extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public bool $countsAsPresent = false;

    public bool $countsTowardPercentage = true;

    public bool $isAuthorised = true;

    public bool $requiresDocument = false;

    public bool $suppressesNotification = false;

    public bool $triggersWelfareFlag = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.attendance.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.attendance.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        app(CreateAttendanceReasonCodeAction::class)->execute(new CreateAttendanceReasonCodeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            countsAsPresent: $this->countsAsPresent,
            countsTowardPercentage: $this->countsTowardPercentage,
            isAuthorised: $this->isAuthorised,
            requiresDocument: $this->requiresDocument,
            suppressesNotification: $this->suppressesNotification,
            triggersWelfareFlag: $this->triggersWelfareFlag,
        ));

        $this->reset(['code', 'name', 'countsAsPresent', 'requiresDocument', 'suppressesNotification', 'triggersWelfareFlag']);
        $this->toast(__('Reason code created.'));
    }

    public function render(): View
    {
        return view('academic::attendance.reason-codes', [
            'reasonCodes' => AttendanceReasonCode::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
