<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ProcessExaminationResultsAction;
use Modules\Academic\Domain\Actions\PublishExaminationResultsAction;
use Modules\Academic\Domain\DataObjects\ProcessExaminationResultsData;
use Modules\Academic\Domain\DataObjects\PublishExaminationResultsData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exams\Results` (Book E ACA-07 §4/§8 ⭐/BR-ACA-07-016/017,
 * `academic.exams.results_process` to process, `academic.exams.results_publish`
 * ⚠ to publish). One lifecycle screen: process aggregates paper marks
 * into `ACA-05` as an `examination`-category assessment — refusing,
 * naming the subject and shortfall, when component weights don't total
 * 100% (`AC-ACA-07-011`) — then the separate, staged publish step
 * (`results_ready` → `published`) makes them visible to learners and
 * guardians. "Analysis" (distributions, year-on-year) is deliberately
 * not built — no Action computes it.
 */
#[Title('Examination results')]
#[Layout('layouts.app')]
final class Results extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.results_process');
    }

    public function process(int $sessionId): void
    {
        try {
            app(ProcessExaminationResultsAction::class)->execute(new ProcessExaminationResultsData(
                sessionId: $sessionId,
                processedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Results processed — marks now feed the ACA-05 aggregation pipeline.'));
    }

    public function publish(int $sessionId): void
    {
        $this->authorizePermission('academic.exams.results_publish');

        try {
            app(PublishExaminationResultsAction::class)->execute(new PublishExaminationResultsData(
                sessionId: $sessionId,
                publishedByUserId: (int) auth()->id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Results published — visible to learners and guardians.'));
    }

    public function render(): View
    {
        return view('academic::exams.results', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)
                ->whereIn('status', ['in_progress', 'marking', 'moderation', 'results_ready', 'published'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
