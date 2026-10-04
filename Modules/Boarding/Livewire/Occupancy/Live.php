<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Occupancy;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Support\LiveOccupancyProvider;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Occupancy\Live` (Book F BRD-02 §6, `boarding.rollcall.view`). The
 * same `LiveOccupancyProvider` interface `BRD-04` catering and
 * `OPS-06`'s muster roll both consume — this screen just renders it
 * per hostel for a chosen date, so staff can see the real feed
 * driving those other modules.
 */
#[Title('Live occupancy')]
#[Layout('layouts.app')]
final class Live extends Component
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
        $provider = app(LiveOccupancyProvider::class);
        $date = Carbon::parse($this->date !== '' ? $this->date : now()->toDateString());

        $rows = Hostel::where('school_id', $this->school->id)->orderBy('name')->get()
            ->map(fn (Hostel $hostel) => [
                'hostel' => $hostel,
                'occupancy' => $provider->liveOccupancy($date, 'lunch', $hostel->id),
            ]);

        return view('boarding::occupancy.live', [
            'rows' => $rows,
            'overall' => $provider->liveOccupancy($date, 'lunch'),
        ]);
    }
}
