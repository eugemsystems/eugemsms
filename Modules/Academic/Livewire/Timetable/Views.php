<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ExportTimetableViewAction;
use Modules\Academic\Domain\DataObjects\ExportTimetableViewData;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Timetable\Views` (Book E ACA-03 §7/BR-ACA-03-022, `academic.timetable.view`).
 * One screen, a filter mode (class/teacher/venue) standing in for the
 * spec's separate by-class/teacher/venue/learner/department views — same
 * data, same table, different `where()`. "Printable and exportable"
 * (BR-ACA-03-022) used to stop at the browser's own print dialog;
 * **gap closed**: `export()` now also offers a PDF of the same table via
 * `ExportTimetableViewAction`, now that `barryvdh/laravel-dompdf` is a
 * dependency (added for Book J INT-01's report export).
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

    public function export(): ?Response
    {
        $this->authorizePermission('academic.timetable.view');

        if ($this->timetableId === null || $this->targetId === null) {
            return null;
        }

        $pdf = app(ExportTimetableViewAction::class)->execute(new ExportTimetableViewData(
            timetableId: $this->timetableId, mode: $this->mode, targetId: $this->targetId,
        ));

        $timetable = Timetable::find($this->timetableId);
        $timetableName = $timetable === null ? 'timetable' : $timetable->name;
        $slug = Str::slug($timetableName) !== '' ? Str::slug($timetableName) : 'timetable';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$slug}.pdf\"",
        ]);
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
