<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Counselling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\EscalateCounsellingSessionAction;
use Modules\Welfare\Domain\Actions\RecordCounsellingSessionAction;
use Modules\Welfare\Domain\DataObjects\RecordCounsellingSessionData;
use Modules\Welfare\Livewire\Safeguarding\Concerns\BlocksVendorAndImpersonation;
use Modules\Welfare\Models\CounsellingSession;

/**
 * `Counselling\Diary` (Book G BRD-08 §6, `safeguarding.counselling.record`
 * — counsellor, own sessions). Folds the spec's separate "Session
 * record" screen into this diary — viewing/editing a single row is the
 * same form as recording a new one. Filtered to the CURRENT user's own
 * `counsellor_staff_id` only (BR-BRD-08-015 — "visible to the recording
 * counsellor and the safeguarding lead only. Not to the head by
 * default"); a lead-wide view is deliberately not built here, since no
 * action exists to list "every counsellor's sessions" and building one
 * would be the first screen in this codebase to query
 * `CounsellingSession.session_notes` across counsellors without an
 * audited per-session read path — left for a future pass if the lead
 * genuinely needs it.
 */
#[Title('Counselling diary')]
#[Layout('layouts.app')]
final class Diary extends Component
{
    use AuthorizesPermissions;
    use BlocksVendorAndImpersonation;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $sessionType = 'individual';

    public bool $riskIndicatorsPresent = false;

    public ?string $referralSource = null;

    public ?string $presentingTheme = null;

    public ?string $sessionNotes = null;

    public ?string $nextSessionOn = null;

    public bool $attended = true;

    public ?int $escalatingSessionId = null;

    public string $escalationSummary = '';

    public string $escalationRiskLevel = 'medium';

    public ?Staff $staff = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->abortIfVendorOrImpersonating();
        $this->authorizePermission('safeguarding.counselling.record');

        $this->staff = Staff::where('user_id', Auth::id())->where('school_id', $school->id)->first();
    }

    public function record(): void
    {
        abort_if($this->staff === null, 403, __('No staff record is linked to your account.'));

        $this->validate([
            'studentId' => ['required', 'integer'],
            'sessionType' => ['required', 'string'],
        ]);

        app(RecordCounsellingSessionAction::class)->execute(new RecordCounsellingSessionData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            counsellorStaffId: $this->staff->id,
            sessionAt: Carbon::now(),
            sessionType: $this->sessionType,
            riskIndicatorsPresent: $this->riskIndicatorsPresent,
            referralSource: $this->referralSource,
            presentingTheme: $this->presentingTheme,
            sessionNotes: $this->sessionNotes,
            nextSessionOn: $this->nextSessionOn !== null && $this->nextSessionOn !== '' ? Carbon::parse($this->nextSessionOn) : null,
            attended: $this->attended,
        ));

        $this->reset(['presentingTheme', 'sessionNotes', 'nextSessionOn', 'riskIndicatorsPresent', 'referralSource']);
        $this->toast(__('Session recorded.'));
    }

    public function escalate(int $sessionId): void
    {
        abort_if($this->staff === null, 403);

        $this->validate(['escalationSummary' => ['required', 'string']]);

        app(EscalateCounsellingSessionAction::class)->execute(
            $sessionId,
            $this->staff->id,
            (int) Auth::id(),
            $this->escalationSummary,
            $this->escalationRiskLevel,
        );

        $this->reset(['escalatingSessionId', 'escalationSummary']);
        $this->toast(__('Escalated to a safeguarding case.'));
    }

    public function render(): View
    {
        return view('welfare::counselling.diary', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'sessions' => $this->staff !== null
                ? CounsellingSession::where('counsellor_staff_id', $this->staff->id)->with('student:id,first_name,last_name')->orderByDesc('session_at')->limit(100)->get()
                : collect(),
        ]);
    }
}
