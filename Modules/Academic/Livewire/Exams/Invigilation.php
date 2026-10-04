<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AssignInvigilationAction;
use Modules\Academic\Domain\DataObjects\AssignInvigilationData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\InvigilationAssignment;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\Invigilation` (Book E ACA-07 §4/BR-ACA-07-010/011,
 * `academic.exams.manage`). Assign + roster, with the subject-teacher
 * exclusion warning `AssignInvigilationAction` itself enforces
 * (refuses unless `overrideSubjectTeacherExclusion` is checked) —
 * surfaced here as a checkbox the assigner must tick deliberately.
 */
#[Title('Invigilation')]
#[Layout('layouts.app')]
final class Invigilation extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $paperId = null;

    public ?int $venueId = null;

    public ?int $staffId = null;

    public string $role = 'chief';

    public bool $overrideExclusion = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.manage');
    }

    public function assign(): void
    {
        $this->validate([
            'paperId' => ['required', 'integer'],
            'venueId' => ['required', 'integer'],
            'staffId' => ['required', 'integer'],
        ]);

        try {
            app(AssignInvigilationAction::class)->execute(new AssignInvigilationData(
                paperId: $this->paperId,
                venueId: $this->venueId,
                staffId: $this->staffId,
                role: $this->role,
                overrideSubjectTeacherExclusion: $this->overrideExclusion,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['staffId', 'overrideExclusion']);
        $this->toast(__('Invigilator assigned.'));
    }

    public function render(): View
    {
        return view('academic::exams.invigilation', [
            'papers' => ExaminationPaper::where('school_id', $this->school->id)->with('subject')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'assignments' => $this->paperId !== null
                ? InvigilationAssignment::where('paper_id', $this->paperId)->with('staff', 'venue')->get()
                : collect(),
        ]);
    }
}
