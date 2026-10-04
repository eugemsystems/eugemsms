<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ConfirmExaminationCandidateAction;
use Modules\Academic\Domain\Actions\DeriveExaminationCandidatesAction;
use Modules\Academic\Domain\DataObjects\ConfirmExaminationCandidateData;
use Modules\Academic\Domain\DataObjects\DeriveExaminationCandidatesData;
use Modules\Academic\Models\ExaminationCandidate;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exams\Candidates` (Book E ACA-07 §2/§4/BR-ACA-07-001/002,
 * `academic.exams.manage`). Derive (from `ACA-02` enrolments — never
 * typed from scratch) + confirm, which allocates the gapless, immutable
 * index number. Entry-fee billing (`FIN-02`) is a documented backend
 * gap — not surfaced here.
 */
#[Title('Examination candidates')]
#[Layout('layouts.app')]
final class Candidates extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sessionId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.manage');
    }

    public function derive(): void
    {
        if ($this->sessionId === null) {
            return;
        }

        $candidates = app(DeriveExaminationCandidatesAction::class)->execute(new DeriveExaminationCandidatesData(
            sessionId: $this->sessionId,
        ));

        $this->toast(__(':count candidate(s) derived from current subject enrolments.', ['count' => $candidates->count()]));
    }

    public function confirm(int $candidateId): void
    {
        try {
            app(ConfirmExaminationCandidateAction::class)->execute(new ConfirmExaminationCandidateData(
                candidateId: $candidateId,
                confirmedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Candidate confirmed — index number allocated.'));
    }

    public function render(): View
    {
        return view('academic::exams.candidates', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'candidates' => $this->sessionId !== null
                ? ExaminationCandidate::where('session_id', $this->sessionId)->with('student')->get()
                : collect(),
        ]);
    }
}
