<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\EnterExamMarkAction;
use Modules\Academic\Domain\DataObjects\EnterExamMarkData;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\MarkEntry` (Book E ACA-07 §4/BR-ACA-07-013/015,
 * `academic.exams.mark`). Entry by paper. When double marking is on,
 * `EnterExamMarkAction` itself never returns the first marker's value
 * to this or any caller — this screen cannot show it even if it wanted
 * to, which is the "blind" the spec asks for structurally, not by
 * convention.
 */
#[Title('Mark entry')]
#[Layout('layouts.app')]
final class MarkEntry extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $paperId = null;

    /** @var array<int, string> */
    public array $marks = [];

    /** @var array<int, bool> */
    public array $absentFlags = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.mark');
    }

    public function save(int $candidateId): void
    {
        $isAbsent = $this->absentFlags[$candidateId] ?? false;
        $mark = $this->marks[$candidateId] ?? '';

        if (! $isAbsent && $mark === '') {
            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(EnterExamMarkAction::class)->execute(new EnterExamMarkData(
                paperId: $this->paperId,
                candidateId: $candidateId,
                markerStaffId: $staffId,
                mark: ! $isAbsent ? (float) $mark : null,
                isAbsent: $isAbsent,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->marks[$candidateId], $this->absentFlags[$candidateId]);
        $this->toast(__('Mark saved.'));
    }

    public function render(): View
    {
        $paper = $this->paperId !== null ? ExaminationPaper::find($this->paperId) : null;

        $candidates = $paper !== null
            ? ExaminationCandidate::where('session_id', $paper->session_id)
                ->where('entry_status', 'confirmed')
                ->get()
                ->filter(fn (ExaminationCandidate $c): bool => in_array($paper->subject_id, $c->entered_subjects, true))
                ->load('student')
            : collect();

        $existingMarks = $paper !== null
            ? ExaminationMark::where('paper_id', $paper->id)->get()->keyBy('candidate_id')
            : collect();

        return view('academic::exams.mark-entry', [
            'papers' => ExaminationPaper::where('school_id', $this->school->id)->with('subject')->get(),
            'candidates' => $candidates,
            'existingMarks' => $existingMarks,
        ]);
    }
}
