<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Hostels;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelDamage;
use Modules\Boarding\Models\RoomInspection;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Hostels\Show` (Book F BRD-01 §5, `boarding.hostel.view`). Read-only
 * profile — staff, derived occupancy, recent inspections and damages
 * — linking out to `Inspections\Index`/`Damages\Index` for the real
 * management actions rather than duplicating them here, the same way
 * `People\Students\Guardians` links out to PPL-03's own editor.
 */
#[Title('Hostel profile')]
#[Layout('layouts.app')]
final class Show extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Hostel $hostel;

    public function mount(School $school, Hostel $hostel): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.hostel.view');
        $this->hostel = $hostel;
    }

    public function render(): View
    {
        $occupied = BedAllocation::where('hostel_id', $this->hostel->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->count();

        return view('boarding::hostels.show', [
            'occupied' => $occupied,
            'inspections' => RoomInspection::whereHas('room', fn ($q) => $q->where('hostel_id', $this->hostel->id))
                ->orderByDesc('inspection_date')->limit(10)->get(),
            'damages' => HostelDamage::where('hostel_id', $this->hostel->id)->orderByDesc('reported_at')->limit(10)->get(),
        ]);
    }
}
