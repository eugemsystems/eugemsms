<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\AdvanceExaminationSessionStatusAction;
use Modules\Academic\Domain\Actions\CreateExaminationSessionAction;
use Modules\Academic\Domain\DataObjects\AdvanceExaminationSessionStatusData;
use Modules\Academic\Domain\DataObjects\CreateExaminationSessionData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;

/**
 * `Exams\Sessions` (Book E ACA-07 §2/§5/§6, `academic.exams.manage`).
 * List + create, plus the forward-only status advance this pass's own
 * `AdvanceExaminationSessionStatusAction` closed a gap for (see that
 * action's own docblock) — a session must reach `in_progress` before
 * `ProcessExaminationResultsAction` will accept it, and nothing else in
 * the domain layer ever moved it there.
 */
#[Title('Examination sessions')]
#[Layout('layouts.app')]
final class Sessions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public string $name = '';

    public string $examType = 'end_of_term';

    public string $examBody = 'internal';

    public string $startsOn = '';

    public string $endsOn = '';

    /** @var array<int, int> */
    public array $affectedLevels = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.manage');
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'examType' => ['required', 'string'],
            'examBody' => ['required', 'in:internal,zimsec,cambridge'],
            'startsOn' => ['required', 'date'],
            'endsOn' => ['required', 'date', 'after_or_equal:startsOn'],
            'affectedLevels' => ['required', 'array', 'min:1'],
        ]);

        $yearId = SessionContext::yearId();
        $termId = SessionContext::termId();

        if ($yearId === null || $termId === null) {
            $this->toast(__('No active academic year/term is set for this school.'), 'danger');

            return;
        }

        app(CreateExaminationSessionAction::class)->execute(new CreateExaminationSessionData(
            schoolId: $this->school->id,
            academicYearId: $yearId,
            termId: $termId,
            name: $this->name,
            examType: $this->examType,
            examBody: $this->examBody,
            affectedLevels: $this->affectedLevels,
            startsOn: Carbon::parse($this->startsOn),
            endsOn: Carbon::parse($this->endsOn),
            createdBy: (int) Auth::id(),
        ));

        $this->reset(['name', 'startsOn', 'endsOn', 'affectedLevels']);
        $this->toast(__('Examination session created.'));
    }

    public function advance(int $sessionId, string $toStatus): void
    {
        try {
            app(AdvanceExaminationSessionStatusAction::class)->execute(new AdvanceExaminationSessionStatusData(
                sessionId: $sessionId,
                toStatus: $toStatus,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Session moved to :status.', ['status' => $toStatus]));
    }

    public function render(): View
    {
        return view('academic::exams.sessions', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'levels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
        ]);
    }
}
