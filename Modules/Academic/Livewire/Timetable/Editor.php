<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTimetableSlotAction;
use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableSlot;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\People\Models\Staff;

/**
 * `Timetable\Editor` (Book E ACA-03 §3/§7 ⭐, `academic.timetable.edit`).
 * The spec asks for drag-and-drop with a clash panel updating as the
 * tile moves. This pass builds a plain add-one-slot form instead — the
 * same "real UX investment the backend doesn't require" simplification
 * `Marks\Entry` (Book D) made for its own grid. `CreateTimetableSlotAction`
 * already runs the real four-level clash check server-side and refuses
 * with the specific conflict named (`TimetableSlotClashException`); this
 * screen surfaces that refusal inline rather than live during a drag
 * that doesn't exist here. No drag, no undo — see `.ai/rules/academic.md`.
 */
#[Title('Timetable editor')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Timetable $timetable;

    public string $cycleDay = '1';

    public string $periodNumber = '1';

    public ?int $subjectId = null;

    public ?int $staffId = null;

    public ?int $classId = null;

    public ?int $venueId = null;

    public bool $isLocked = false;

    public function mount(School $school, Timetable $timetable): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.view');

        abort_unless($timetable->school_id === $school->id, 404);

        $this->timetable = $timetable;
    }

    public function addSlot(): void
    {
        $this->authorizePermission('academic.timetable.edit');

        $this->validate([
            'cycleDay' => ['required', 'integer', 'min:1'],
            'periodNumber' => ['required', 'integer', 'min:1'],
            'subjectId' => ['required', 'integer'],
            'staffId' => ['required', 'integer'],
        ]);

        $periodSlot = PeriodSlot::where('structure_id', $this->timetable->structure_id)
            ->where('cycle_day', $this->cycleDay)
            ->where('period_number', $this->periodNumber)
            ->first();

        if ($periodSlot === null) {
            $this->toast(__('No period slot exists for that cycle day/period on this structure.'), 'danger');

            return;
        }

        try {
            app(CreateTimetableSlotAction::class)->execute(new CreateTimetableSlotData(
                timetableId: $this->timetable->id,
                termId: $this->timetable->term_id,
                periodSlotId: $periodSlot->id,
                cycleDay: (int) $this->cycleDay,
                periodNumber: (int) $this->periodNumber,
                subjectId: $this->subjectId,
                staffId: $this->staffId,
                classId: $this->classId,
                venueId: $this->venueId,
                isLocked: $this->isLocked,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['subjectId', 'staffId', 'classId', 'venueId', 'isLocked']);
        $this->toast(__('Lesson placed.'));
    }

    public function render(): View
    {
        return view('academic::timetable.editor', [
            'slots' => TimetableSlot::where('timetable_id', $this->timetable->id)
                ->orderBy('cycle_day')->orderBy('period_number')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->orderBy('name')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
