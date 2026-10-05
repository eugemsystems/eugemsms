<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\ExitInterviews;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CompleteExitInterviewAction;
use Modules\Comms\Domain\Actions\DeclineExitInterviewAction;
use Modules\Comms\Domain\Actions\RequestExitInterviewAction;
use Modules\Comms\Models\ExitInterview;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Comms\ExitInterviews\Index` (Book I COM-08 §4, `complaints.manage`).
 * An exit interview is offered, never forced (BR-COM-08-007): a request
 * can be completed (by survey or by call) or declined, and a decline is
 * recorded exactly like a completion because the school-wide decline
 * rate is itself data (AC-COM-08-005) — the response-rate panel counts
 * both. Only a still-pending request can be completed or declined.
 * Withdrawal itself is PPL-01's flow; the learner is chosen by
 * admission number.
 */
#[Title('Exit interviews')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    /** @var array<string, string> */
    public const array REASONS = [
        'relocation' => 'Relocation',
        'fees' => 'Fees',
        'academic_fit' => 'Academic fit',
        'boarding_experience' => 'Boarding experience',
        'other_school' => 'Moving to another school',
        'dissatisfaction' => 'Dissatisfaction',
    ];

    public string $admissionNumber = '';

    public ?int $completingId = null;

    public string $primaryReason = '';

    public string $responseSource = 'call';

    public string $detail = '';

    public string $wouldRecommend = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('complaints.manage');
    }

    public function request(): void
    {
        $this->authorizePermission('complaints.manage');

        $this->validate(['admissionNumber' => ['required', 'string', 'max:40']]);

        $student = Student::where('school_id', $this->school->id)->where('admission_number', $this->admissionNumber)->first();

        if ($student === null) {
            $this->addError('admissionNumber', __('No learner has that admission number.'));

            return;
        }

        if (ExitInterview::where('school_id', $this->school->id)->where('student_id', $student->id)->whereNull('completed_at')->exists()) {
            $this->addError('admissionNumber', __('There is already a pending exit interview for this learner.'));

            return;
        }

        app(RequestExitInterviewAction::class)->execute($this->school->id, $student->id);

        $this->reset('admissionNumber');
        $this->toast(__('Exit interview offered.'));
    }

    public function complete(): void
    {
        $this->authorizePermission('complaints.manage');

        $this->validate([
            'completingId' => ['required', 'integer'],
            'primaryReason' => ['required', 'in:'.implode(',', array_keys(self::REASONS))],
            'responseSource' => ['required', 'in:survey,call'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'wouldRecommend' => ['nullable', 'in:yes,no'],
        ]);

        $interview = ExitInterview::where('school_id', $this->school->id)->whereNull('completed_at')->findOrFail($this->completingId);

        app(CompleteExitInterviewAction::class)->execute(
            $interview->id,
            $this->primaryReason,
            $this->responseSource,
            $this->detail !== '' ? $this->detail : null,
            $this->wouldRecommend === '' ? null : $this->wouldRecommend === 'yes',
        );

        $this->reset(['completingId', 'primaryReason', 'detail', 'wouldRecommend']);
        $this->toast(__('Exit interview recorded.'));
    }

    public function decline(int $interviewId): void
    {
        $this->authorizePermission('complaints.manage');

        $interview = ExitInterview::where('school_id', $this->school->id)->whereNull('completed_at')->findOrFail($interviewId);
        app(DeclineExitInterviewAction::class)->execute($interview->id);

        $this->toast(__('Decline recorded — it counts towards the response rate.'));
    }

    public function render(): View
    {
        $interviews = ExitInterview::where('school_id', $this->school->id)->orderByDesc('id')->limit(100)->get();
        $students = Student::where('school_id', $this->school->id)->whereIn('id', $interviews->pluck('student_id'))->get()->keyBy('id');

        $declined = ExitInterview::where('school_id', $this->school->id)->where('response_source', 'declined')->count();
        $completed = ExitInterview::where('school_id', $this->school->id)->whereNotNull('completed_at')->where('response_source', '!=', 'declined')->count();
        $answered = $declined + $completed;

        return view('comms::exit-interviews.index', [
            'interviews' => $interviews,
            'students' => $students,
            'reasons' => self::REASONS,
            'completed' => $completed,
            'declined' => $declined,
            'pending' => ExitInterview::where('school_id', $this->school->id)->whereNull('completed_at')->count(),
            'declineRate' => $answered > 0 ? (int) round($declined / $answered * 100) : null,
        ]);
    }
}
