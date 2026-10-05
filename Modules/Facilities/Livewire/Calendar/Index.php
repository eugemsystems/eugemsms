<?php

declare(strict_types=1);

namespace Modules\Facilities\Livewire\Calendar;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Facilities\Models\BookableResource;
use Modules\Facilities\Models\ResourceBooking;

/**
 * `Calendar\Index` (Book H2 OPS-05 §4, `facilities.view`). Week view,
 * all resources — a plain read: the timetable overlay the spec names
 * is `CheckResourceAvailabilityAction`'s own concern at booking time
 * (`Request\Index`), not a second rendering of `ACA-03`'s timetable
 * here.
 */
#[Title('Resource calendar')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $weekStart = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('facilities.view');
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function render(): View
    {
        $start = Carbon::parse($this->weekStart)->startOfDay();
        $end = $start->copy()->addDays(7);

        return view('facilities::calendar.index', [
            'bookings' => ResourceBooking::with('resource')
                ->where('school_id', $this->school->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->whereBetween('starts_at', [$start, $end])
                ->orderBy('starts_at')
                ->get(),
            'resources' => BookableResource::where('school_id', $this->school->id)->orderBy('code')->get(),
        ]);
    }
}
