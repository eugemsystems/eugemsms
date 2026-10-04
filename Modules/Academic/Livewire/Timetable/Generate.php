<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTimetableAction;
use Modules\Academic\Domain\Actions\GenerateTimetableAction;
use Modules\Academic\Domain\DataObjects\CreateTimetableData;
use Modules\Academic\Domain\DataObjects\GenerateTimetableData;
use Modules\Academic\Models\PeriodStructure;
use Modules\Academic\Models\Timetable;
use Modules\Academic\Models\TimetableGenerationRun;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;

/**
 * `Timetable\Generate` (Book E ACA-03 §4/§7, `academic.timetable.manage`
 * to create a draft, `academic.timetable.generate` to run it). Folds the
 * spec's implicit "create a timetable" step (no screen of its own in the
 * spec — see `CreateTimetableAction`'s own docblock for the gap this
 * closed) together with the generation run itself. `GenerateTimetableAction`
 * runs synchronously in this codebase, not as a queued job with live
 * progress/a score curve/a cancel button the spec describes — a
 * deliberate simplification matching `GenerateTimetableAction`'s own
 * documented scope (no job, no annealing-with-temperature-schedule). The
 * run's own `unplaced_requirements` is shown in full, by name, exactly as
 * BR-ACA-03-005 requires.
 */
#[Title('Generate timetable')]
#[Layout('layouts.app')]
final class Generate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $name = '';

    public ?int $structureId = null;

    public ?int $selectedTimetableId = null;

    public ?int $lastRunId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function createDraft(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'structureId' => ['required', 'integer'],
        ]);

        $termId = SessionContext::termId();
        $yearId = SessionContext::yearId();

        if ($termId === null || $yearId === null) {
            $this->toast(__('No active academic year/term is set for this school.'), 'danger');

            return;
        }

        $timetable = app(CreateTimetableAction::class)->execute(new CreateTimetableData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            structureId: $this->structureId,
            name: $this->name,
            createdByUserId: (int) Auth::id(),
        ));

        $this->selectedTimetableId = $timetable->id;
        $this->reset(['name', 'structureId']);
        $this->toast(__('Draft timetable created.'));
    }

    public function generate(): void
    {
        $this->authorizePermission('academic.timetable.generate');

        if ($this->selectedTimetableId === null) {
            $this->toast(__('Select a timetable first.'), 'danger');

            return;
        }

        $run = app(GenerateTimetableAction::class)->execute(new GenerateTimetableData(
            timetableId: $this->selectedTimetableId,
            requestedByUserId: (int) Auth::id(),
        ));

        $this->lastRunId = $run->id;

        $unplacedCount = is_array($run->unplaced_requirements) ? count($run->unplaced_requirements) : 0;
        $this->toast($unplacedCount > 0
            ? __(':count requirement(s) could not be placed — see the report below.', ['count' => $unplacedCount])
            : __('Generation completed with zero hard violations.'));
    }

    public function render(): View
    {
        return view('academic::timetable.generate', [
            'structures' => PeriodStructure::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'timetables' => Timetable::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'lastRun' => $this->lastRunId !== null ? TimetableGenerationRun::find($this->lastRunId) : null,
        ]);
    }
}
