<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\ClearExitChecklistItemAction;
use Modules\People\Domain\Actions\InitiateStaffExitAction;
use Modules\People\Domain\Actions\ProcessStaffExitAction;
use Modules\People\Domain\Actions\ReleaseFinalPayAction;
use Modules\People\Domain\DataObjects\ClearExitChecklistItemData;
use Modules\People\Domain\DataObjects\InitiateStaffExitData;
use Modules\People\Domain\DataObjects\ProcessStaffExitData;
use Modules\People\Domain\DataObjects\ReleaseFinalPayData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffExitChecklist;

/**
 * `People\Staff\ExitProcessing` (Book C PPL-04 §5/BR-PPL-04-021/022,
 * `people.staff.exit_process`). Named `ExitProcessing`, not `Exit` —
 * `exit` is a reserved PHP language construct and cannot be a class
 * name (`php -l` confirms the parse error). Three independent actions
 * on one screen: initiating (seeds the checklist), clearing checklist
 * items and releasing final pay (gated on full clearance,
 * AC-PPL-04-008), and processing the actual exit (account
 * deactivation/token revocation) — the last runs regardless of
 * checklist state, per `ProcessStaffExitAction`'s own docblock.
 */
#[Title('Staff exit')]
#[Layout('layouts.app')]
final class ExitProcessing extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Staff $staff;

    public string $exitedOn = '';

    public string $exitReason = '';

    public function mount(School $school, Staff $staff): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.staff.exit_process');

        abort_unless($staff->school_id === $school->id, 404);

        $this->staff = $staff;
        $this->exitedOn = now()->toDateString();
    }

    public function initiate(): void
    {
        app(InitiateStaffExitAction::class)->execute(new InitiateStaffExitData(
            staffId: $this->staff->id,
            initiatedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Exit clearance initiated.'));
    }

    public function clearItem(int $checklistId, string $itemCode): void
    {
        app(ClearExitChecklistItemAction::class)->execute(new ClearExitChecklistItemData(
            checklistId: $checklistId,
            itemCode: $itemCode,
            clearedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Item cleared.'));
    }

    public function releaseFinalPay(int $checklistId): void
    {
        try {
            app(ReleaseFinalPayAction::class)->execute(new ReleaseFinalPayData(
                checklistId: $checklistId,
                releasedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Final pay released.'));
    }

    public function processExit(): void
    {
        $this->validate(['exitedOn' => ['required', 'date'], 'exitReason' => ['required', 'string', 'max:60']]);

        app(ProcessStaffExitAction::class)->execute(new ProcessStaffExitData(
            staffId: $this->staff->id,
            exitReason: $this->exitReason,
            exitedOn: Carbon::parse($this->exitedOn),
            processedByUserId: (int) Auth::id(),
        ));

        $this->staff = $this->staff->fresh();
        $this->toast(__('Staff member processed as exited.'));
    }

    public function render(): View
    {
        return view('people::staff.exit', [
            'checklist' => StaffExitChecklist::where('staff_id', $this->staff->id)->orderByDesc('id')->first(),
        ]);
    }
}
