<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\ApproveSpecialArrangementAction;
use Modules\Academic\Domain\Actions\RecordSpecialArrangementAction;
use Modules\Academic\Domain\DataObjects\ApproveSpecialArrangementData;
use Modules\Academic\Domain\DataObjects\RecordSpecialArrangementData;
use Modules\Academic\Models\ExaminationSession;
use Modules\Academic\Models\SpecialArrangement;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Exams\Arrangements` (Book E ACA-07 §4/BR-ACA-07-009,
 * `academic.exams.arrangements_manage`). Record (requested) + approve —
 * `ApproveSpecialArrangementAction` is the only way an arrangement
 * becomes effective for seating/invigilation (`Exams\Seating` reads
 * only `status = approved` rows).
 */
#[Title('Special arrangements')]
#[Layout('layouts.app')]
final class Arrangements extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sessionId = null;

    public ?int $studentId = null;

    public string $arrangementType = 'extra_time';

    public string $extraTimePercent = '';

    public string $justification = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.arrangements_manage');
    }

    public function record(): void
    {
        $this->validate([
            'sessionId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
            'arrangementType' => ['required', 'string'],
            'justification' => ['required', 'string', 'min:10'],
        ]);

        app(RecordSpecialArrangementAction::class)->execute(new RecordSpecialArrangementData(
            schoolId: $this->school->id,
            sessionId: $this->sessionId,
            studentId: $this->studentId,
            arrangementType: $this->arrangementType,
            justification: $this->justification,
            extraTimePercent: $this->extraTimePercent !== '' ? (int) $this->extraTimePercent : null,
        ));

        $this->reset(['studentId', 'extraTimePercent', 'justification']);
        $this->toast(__('Arrangement recorded as requested — approve it to make it effective.'));
    }

    public function approve(int $arrangementId): void
    {
        try {
            app(ApproveSpecialArrangementAction::class)->execute(new ApproveSpecialArrangementData(
                arrangementId: $arrangementId,
                approvedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast(__('Arrangement approved.'));
    }

    public function render(): View
    {
        return view('academic::exams.arrangements', [
            'sessions' => ExaminationSession::where('school_id', $this->school->id)->orderByDesc('id')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(200)->get(),
            'arrangements' => $this->sessionId !== null
                ? SpecialArrangement::where('session_id', $this->sessionId)->with('student')->get()
                : collect(),
        ]);
    }
}
