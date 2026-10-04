<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\RollCall;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\RollCall;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `RollCall\Board` (Book F BRD-02 §6, `boarding.rollcall.view`). Every
 * hostel's roll calls for a chosen date — defaults to today, and also
 * stands in for the spec's separate "Roll call history" screen (a
 * date picker over the same table), the same fold `ACA-04`'s own
 * `Attendance\Daily` uses.
 */
#[Title('Roll call board')]
#[Layout('layouts.app')]
final class Board extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $date = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.rollcall.view');
        $this->date = now()->toDateString();
    }

    public function render(): View
    {
        return view('boarding::rollcall.board', [
            'rollCalls' => RollCall::where('school_id', $this->school->id)
                ->whereDate('roll_date', $this->date !== '' ? $this->date : Carbon::now()->toDateString())
                ->with('hostel', 'point')
                ->orderBy('scheduled_at')
                ->get(),
        ]);
    }
}
