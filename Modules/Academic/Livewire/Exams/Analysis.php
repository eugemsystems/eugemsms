<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AnalyseExaminationSessionAction;
use Modules\Academic\Domain\DataObjects\AnalyseExaminationSessionData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exams\Analysis` (Book E ACA-07 §5, `academic.exams.view`, reusing
 * the existing read permission rather than minting a near-duplicate
 * `results_view`). Grade distribution, subject comparison, and
 * year-on-year trend, all computed by `AnalyseExaminationSessionAction`
 * from marks `ProcessExaminationResultsAction` already settled —
 * gap-closing: `Exams\Results`' own docblock used to say no Action
 * computed this.
 */
#[Title('Examination analysis')]
#[Layout('layouts.app')]
final class Analysis extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $sessionId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.view');
    }

    public function render(): View
    {
        $report = $this->sessionId === null ? null : app(AnalyseExaminationSessionAction::class)->execute(
            new AnalyseExaminationSessionData(sessionId: $this->sessionId),
        );

        return view('academic::exams.analysis', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)
                ->whereIn('status', ['results_ready', 'published'])
                ->orderByDesc('id')
                ->get(),
            'report' => $report,
        ]);
    }
}
