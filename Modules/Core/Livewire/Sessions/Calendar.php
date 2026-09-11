<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Sessions;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\CalendarHoliday;
use Modules\Core\Models\School;

/**
 * `Core\Sessions\Calendar` (Book A CORE-03 §5). Read-only for now: the
 * holiday calendar `GenerateTermWeeksAction` reads from
 * (`CalendarHoliday`) has no create/edit Action of its own yet in this
 * module — the seed installer (`CalendarSeedPack`) is the only current
 * writer. Adding holiday CRUD needs its own Action first (the
 * "no database write outside an Action" rule), which is a small,
 * separate follow-up, not bundled into this screen under time pressure.
 */
#[Title('Academic calendar')]
#[Layout('layouts.app')]
final class Calendar extends Component
{
    use InteractsWithSchool;
    use InteractsWithSession;

    public ?int $selectedYearId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);

        $this->selectedYearId = AcademicYear::where('school_id', $school->id)->where('is_current', true)->value('id')
            ?? AcademicYear::where('school_id', $school->id)->orderByDesc('starts_on')->value('id');
    }

    public function render(): View
    {
        $years = AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->get();

        $holidays = $this->selectedYearId !== null
            ? CalendarHoliday::where('academic_year_id', $this->selectedYearId)->orderBy('starts_on')->get()
            : collect();

        return view('core::sessions.calendar', [
            'years' => $years,
            'holidays' => $holidays,
        ]);
    }
}
