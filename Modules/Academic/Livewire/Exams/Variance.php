<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\EnterThirdExamMarkAction;
use Modules\Academic\Domain\DataObjects\EnterThirdExamMarkData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\Variance` (Book E ACA-07 §4/BR-ACA-07-013/AC-ACA-07-005,
 * `academic.exams.moderate`). Every mark `EnterExamMarkAction` routed
 * to `variance_review` — a candidate whose first and second marks
 * differed by more than the configured threshold — queued for a third
 * mark.
 */
#[Title('Variance review')]
#[Layout('layouts.app')]
final class Variance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<int, string> */
    public array $thirdMarks = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.moderate');
    }

    public function settle(int $markId): void
    {
        $mark = ExaminationMark::findOrFail($markId);
        $value = $this->thirdMarks[$markId] ?? '';

        if ($value === '') {
            $this->toast(__('A third mark is required.'), 'danger');

            return;
        }

        $staffId = Staff::where('school_id', $this->school->id)->where('user_id', Auth::id())->value('id');

        if ($staffId === null) {
            $this->toast(__('Your account has no staff record linked at this school.'), 'danger');

            return;
        }

        try {
            app(EnterThirdExamMarkAction::class)->execute(new EnterThirdExamMarkData(
                paperId: $mark->paper_id,
                candidateId: $mark->candidate_id,
                markerStaffId: $staffId,
                mark: (float) $value,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->thirdMarks[$markId]);
        $this->toast(__('Third mark settled the candidate.'));
    }

    public function render(): View
    {
        return view('academic::exams.variance', [
            'marks' => ExaminationMark::where('school_id', $this->school->id)
                ->where('status', 'variance_review')
                ->with('student', 'paper.subject')
                ->get(),
        ]);
    }
}
