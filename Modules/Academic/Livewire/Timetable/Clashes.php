<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\DataObjects\TimetableClash;
use Modules\Academic\Domain\Support\TimetableClashDetector;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Timetable\Clashes` (Book E ACA-03 §3/§7 ⭐, `academic.timetable.view`).
 * Runs the real `TimetableClashDetector` (the same four-level check
 * generation and the editor both use) against every slot in the chosen
 * timetable and lists every clash with drill-through names, per the
 * spec's own screen description.
 */
#[Title('Clash inspector')]
#[Layout('layouts.app')]
final class Clashes extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $timetableId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.view');
    }

    public function render(): View
    {
        $clashes = collect();
        $slotLookup = collect();

        if ($this->timetableId !== null) {
            $slots = TimetableSlot::where('timetable_id', $this->timetableId)->get();
            $slotLookup = $slots->keyBy('id');
            $clashes = app(TimetableClashDetector::class)->detect($slots);
        }

        return view('academic::timetable.clashes', [
            'timetables' => Timetable::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'clashes' => $clashes,
            'slotLookup' => $slotLookup,
            'studentNames' => $this->studentNames($clashes),
        ]);
    }

    /**
     * @param  Collection<int, TimetableClash>  $clashes
     * @return array<int, string>
     */
    private function studentNames(Collection $clashes): array
    {
        $ids = $clashes->flatMap(fn (TimetableClash $c): array => $c->sharedLearnerIds)->unique();

        return Student::whereIn('id', $ids)->get()->mapWithKeys(fn (Student $s): array => [$s->id => "{$s->first_name} {$s->last_name}"])->all();
    }
}
