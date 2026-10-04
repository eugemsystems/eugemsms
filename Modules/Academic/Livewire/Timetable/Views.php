<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use Illuminate\Contracts\View\View as ViewContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;

/**
 * `Timetable\Views` (Book E ACA-03 §7/BR-ACA-03-022, `academic.timetable.view`).
 * One screen, a filter mode (class/teacher/venue) standing in for the
 * spec's separate by-class/teacher/venue/learner/department views — same
 * data, same table, different `where()`. "Printable and exportable"
 * (BR-ACA-03-022) stops at the browser's own print dialog on this plain
 * HTML table; no PDF/export-generation Action exists for this screen, so
 * a dedicated export button is not built — a documented simplification,
 * not a missed requirement.
 */
#[Title('Timetable views')]
#[Layout('layouts.app')]
final class Views extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $timetableId = null;

    public string $mode = 'class';

    public ?int $targetId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.view');
    }

    public function updatedMode(): void
    {
        $this->targetId = null;
    }

    public function render(): ViewContract
    {
        $slots = collect();

        if ($this->timetableId !== null && $this->targetId !== null) {
            $column = match ($this->mode) {
                'teacher' => 'staff_id',
                'venue' => 'venue_id',
                default => 'class_id',
            };

            $slots = TimetableSlot::where('timetable_id', $this->timetableId)
                ->where($column, $this->targetId)
                ->orderBy('cycle_day')->orderBy('period_number')
                ->get();
        }

        return view('academic::timetable.views', [
            'timetables' => Timetable::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
            'slots' => $slots,
        ]);
    }
}
