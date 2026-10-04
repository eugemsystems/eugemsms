<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateTimetableConstraintAction;
use Modules\Academic\Domain\DataObjects\CreateTimetableConstraintData;
use Modules\Academic\Models\TimetableConstraint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Timetable\Constraints` (Book E ACA-03 §2/§7, `academic.timetable.manage`).
 * List (grouped by type, as the spec's own screen description asks) +
 * create, with a hard/soft toggle and a weight input standing in for the
 * spec's own "weight sliders" — a plain number input carries the same
 * data without a new JS slider dependency. No `Update` action exists.
 */
#[Title('Timetable constraints')]
#[Layout('layouts.app')]
final class Constraints extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $constraintType = 'teacher_unavailable';

    public string $severity = 'hard';

    public string $weight = '1';

    public string $value = '';

    public string $reason = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'constraintType' => ['required', 'string'],
            'severity' => ['required', 'in:hard,soft'],
            'weight' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        app(CreateTimetableConstraintAction::class)->execute(new CreateTimetableConstraintData(
            schoolId: $this->school->id,
            academicYearId: (int) $this->school->currentAcademicYear()?->id,
            constraintType: $this->constraintType,
            severity: $this->severity,
            weight: (int) $this->weight,
            value: $this->value !== '' ? (int) $this->value : null,
            reason: $this->reason !== '' ? $this->reason : null,
        ));

        $this->reset(['value', 'reason']);
        $this->toast(__('Constraint created.'));
    }

    public function render(): View
    {
        return view('academic::timetable.constraints', [
            'constraintsByType' => TimetableConstraint::where('school_id', $this->school->id)
                ->where('is_active', true)
                ->orderBy('constraint_type')
                ->get()
                ->groupBy('constraint_type'),
        ]);
    }
}
