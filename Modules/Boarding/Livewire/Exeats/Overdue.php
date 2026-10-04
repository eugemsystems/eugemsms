<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Exeats;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CheckOverdueExeatAction;
use Modules\Boarding\Models\Exeat;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exeats\Overdue` (Book F BRD-03 §6, `boarding.exeat.view`). Live
 * overdue returns. "Check now" runs `CheckOverdueExeatAction` on
 * demand per exeat — no scheduled-command wiring exists yet for it
 * (see that action's own docblock), so this is the honest stand-in
 * until a cron entry exists, mirroring `RollCall\Incidents`'
 * "Check ladder" button for the same reason.
 */
#[Title('Overdue returns')]
#[Layout('layouts.app')]
final class Overdue extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.exeat.view');
    }

    public function checkNow(int $exeatId): void
    {
        app(CheckOverdueExeatAction::class)->execute($exeatId);
        $this->toast(__('Checked.'));
    }

    public function render(): View
    {
        return view('boarding::exeats.overdue', [
            'overdue' => Exeat::where('school_id', $this->school->id)
                ->whereIn('status', ['departed', 'overdue'])
                ->where('returns_by', '<', Carbon::now())
                ->with('student', 'exeatType')
                ->orderBy('returns_by')
                ->get(),
        ]);
    }
}
