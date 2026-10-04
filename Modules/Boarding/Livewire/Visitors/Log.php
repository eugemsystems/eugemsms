<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Visitors;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CheckVisitorNotSignedOutAction;
use Modules\Boarding\Models\VisitorLogEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Visitors\Log` (Book F BRD-03 §6, `boarding.visitor.view`).
 * "Check now" runs `CheckVisitorNotSignedOutAction` on demand per
 * entry — no scheduled wiring exists yet for the daily check (see
 * that action's own docblock), mirroring the same stand-in pattern
 * `Exeats\Overdue` and `RollCall\Incidents` both use.
 */
#[Title('Visitor log')]
#[Layout('layouts.app')]
final class Log extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.visitor.view');
    }

    public function checkNow(int $visitorLogId): void
    {
        app(CheckVisitorNotSignedOutAction::class)->execute($visitorLogId);
        $this->toast(__('Checked.'));
    }

    public function render(): View
    {
        return view('boarding::visitors.log', [
            'entries' => VisitorLogEntry::where('school_id', $this->school->id)->with('visitor', 'hostStaff')->orderByDesc('signed_in_at')->limit(100)->get(),
        ]);
    }
}
