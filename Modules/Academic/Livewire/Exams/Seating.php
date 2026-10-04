<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AllocateExamSeatingAction;
use Modules\Academic\Domain\DataObjects\AllocateExamSeatingData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ExaminationSeating;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exams\Seating` (Book E ACA-07 §4/BR-ACA-07-005/006/007,
 * `academic.exams.manage`). Auto-allocate (`AllocateExamSeatingAction`
 * — separate-room arrangements seated first, then the general cohort
 * spaced per setting) and the resulting venue layout, printable via the
 * browser. Also stands in for the spec's separate "Attendance sheets"
 * screen — same seating + special-arrangement data, one more column —
 * rather than a second screen with nothing new to query.
 */
#[Title('Seating plan')]
#[Layout('layouts.app')]
final class Seating extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $paperId = null;

    /** @var array<int, int> */
    public array $venueIds = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.manage');
    }

    public function allocate(): void
    {
        if ($this->paperId === null || $this->venueIds === []) {
            $this->toast(__('Select a paper and at least one venue.'), 'danger');

            return;
        }

        try {
            $seatings = app(AllocateExamSeatingAction::class)->execute(new AllocateExamSeatingData(
                paperId: $this->paperId,
                venueIds: $this->venueIds,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__(':count candidate(s) seated.', ['count' => $seatings->count()]));
    }

    public function render(): View
    {
        $seatings = $this->paperId !== null
            ? ExaminationSeating::where('paper_id', $this->paperId)->with('candidate.student', 'venue')->orderBy('venue_id')->orderBy('seat_number')->get()
            : collect();

        $paper = $this->paperId !== null ? ExaminationPaper::find($this->paperId) : null;

        $arrangements = $paper !== null
            ? SpecialArrangement::where('session_id', $paper->session_id)->where('status', 'approved')->get()->keyBy('student_id')
            : collect();

        return view('academic::exams.seating', [
            'papers' => ExaminationPaper::where('school_id', $this->school->id)->with('subject')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
            'seatings' => $seatings,
            'arrangements' => $arrangements,
        ]);
    }
}
