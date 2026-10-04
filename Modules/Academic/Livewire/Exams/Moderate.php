<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ModerateExamMarkAction;
use Modules\Academic\Domain\DataObjects\ModerateExamMarkData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\Moderate` (Book E ACA-07 §4/BR-ACA-07-014/AC-ACA-07-007,
 * `academic.exams.moderate`). Every `final` mark is sampleable; a
 * moderated mark supersedes the settled mark for aggregation but
 * `raw_mark` is never overwritten — both remain visible, same as
 * `Projects\Moderate`'s own reasoning.
 */
#[Title('Moderate examination marks')]
#[Layout('layouts.app')]
final class Moderate extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $paperId = null;

    /** @var array<int, string> */
    public array $moderatedMarks = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.moderate');
    }

    public function moderate(int $candidateId): void
    {
        $value = $this->moderatedMarks[$candidateId] ?? '';

        if ($value === '' || $this->paperId === null) {
            $this->toast(__('A moderated mark is required.'), 'danger');

            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(ModerateExamMarkAction::class)->execute(new ModerateExamMarkData(
                paperId: $this->paperId,
                candidateId: $candidateId,
                moderatorStaffId: $staffId,
                moderatedMark: (float) $value,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->moderatedMarks[$candidateId]);
        $this->toast(__('Mark moderated.'));
    }

    public function render(): View
    {
        return view('academic::exams.moderate', [
            'papers' => ExaminationPaper::where('school_id', $this->school->id)->with('subject')->get(),
            'marks' => $this->paperId !== null
                ? ExaminationMark::where('paper_id', $this->paperId)->where('status', 'final')->with('student')->get()
                : collect(),
        ]);
    }
}
