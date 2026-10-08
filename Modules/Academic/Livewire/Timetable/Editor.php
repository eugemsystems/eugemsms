<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTimetableSlotAction;
use Modules\Academic\Domain\Actions\MoveTimetableSlotAction;
use Modules\Academic\Domain\Actions\RemoveTimetableSlotAction;
use Modules\Academic\Domain\DataObjects\CreateTimetableSlotData;
use Modules\Academic\Domain\Exceptions\TimetableSlotClashException;
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
 * A class timetable grid (cycle days across, periods down): drag a lesson tile to another cell to
 * move it (`MoveTimetableSlotAction`), or use the add-one-lesson form. A move runs the same four
 * clash levels as placing a lesson and is refused with the conflict named, leaving the lesson where
 * it was; the last move can be undone. Locked, double and published lessons do not move.
 *
 * **Gap closed (2026-10-08): the live drag-over clash panel.** `previewMove()`
 * calls `MoveTimetableSlotAction::preview()` — the same check `moveSlot()`'s
 * own `execute()` runs, just without writing anything — once per cell the
 * dragged tile enters (the view's own Alpine `hoverCell` guard keeps this to
 * one round trip per cell, not one per `dragover` event). The target cell
 * highlights green/red and names the conflict (reusing
 * `TimetableSlotClashException::describe()`, the exact wording a refused drop
 * already used) before the lesson is ever dropped.
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

    public ?int $gridClassId = null;

    /** @var array{slot: int, day: int, period: int}|null */
    public ?array $lastMove = null;

    public ?string $clashMessage = null;

    /** @var array{day: int, period: int, clear: bool, message: ?string}|null */
    public ?array $preview = null;

    public function previewMove(int $slotId, int $cycleDay, int $periodNumber): void
    {
        $this->authorizePermission('academic.timetable.edit');

        $slot = TimetableSlot::query()->where('timetable_id', $this->timetable->id)->find($slotId);

        if ($slot === null) {
            $this->preview = null;

            return;
        }

        $result = app(MoveTimetableSlotAction::class)->preview($slot->id, $cycleDay, $periodNumber);

        $this->preview = [
            'day' => $cycleDay,
            'period' => $periodNumber,
            'clear' => $result->isClear(),
            'message' => $result->blockedReason ?? ($result->clashes->isEmpty() ? null : TimetableSlotClashException::describe($result->clashes)),
        ];
    }

    public function cancelPreview(): void
    {
        $this->preview = null;
    }

    public function moveSlot(int $slotId, int $cycleDay, int $periodNumber): void
    {
        $this->authorizePermission('academic.timetable.edit');
        $this->clashMessage = null;
        $this->preview = null;

        $slot = TimetableSlot::query()->where('timetable_id', $this->timetable->id)->find($slotId);

        if ($slot === null) {
            return;
        }

        $from = ['slot' => $slot->id, 'day' => $slot->cycle_day, 'period' => $slot->period_number];

        try {
            app(MoveTimetableSlotAction::class)->execute($slot->id, $cycleDay, $periodNumber);
        } catch (DomainException $e) {
            $this->clashMessage = $e->getMessage();

            return;
        }

        $this->lastMove = $from;
    }

    public function undoMove(): void
    {
        $this->authorizePermission('academic.timetable.edit');

        if ($this->lastMove === null) {
            return;
        }

        $this->clashMessage = null;

        try {
            app(MoveTimetableSlotAction::class)->execute($this->lastMove['slot'], $this->lastMove['day'], $this->lastMove['period']);
        } catch (DomainException $e) {
            $this->clashMessage = $e->getMessage();

            return;
        }

        $this->lastMove = null;
    }

    public function removeSlot(int $slotId): void
    {
        $this->authorizePermission('academic.timetable.edit');

        $slot = TimetableSlot::query()->where('timetable_id', $this->timetable->id)->find($slotId);

        if ($slot === null) {
            return;
        }

        try {
            app(RemoveTimetableSlotAction::class)->execute($slot->id);
        } catch (DomainException $e) {
            $this->clashMessage = $e->getMessage();

            return;
        }

        $this->lastMove = null;
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
            'gridSlots' => $this->gridClassId === null ? collect() : TimetableSlot::where('timetable_id', $this->timetable->id)->where('class_id', $this->gridClassId)->with('subject:id,name')->get()->groupBy(fn (TimetableSlot $s): string => $s->cycle_day.'-'.$s->period_number),
            'periods' => PeriodSlot::where('structure_id', $this->timetable->structure_id)->where('is_teachable', true)->orderBy('cycle_day')->orderBy('period_number')->get(),
            'subjects' => Subject::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->orderBy('name')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}
