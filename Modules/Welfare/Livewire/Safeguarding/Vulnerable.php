<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Safeguarding;

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
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\AddToVulnerableLearnerRegisterAction;
use Modules\Welfare\Domain\Actions\ReviewVulnerableLearnerAction;
use Modules\Welfare\Domain\DataObjects\AddToVulnerableLearnerRegisterData;
use Modules\Welfare\Models\VulnerableLearnerRegistration;

/**
 * `Safeguarding\Vulnerable` (Book G BRD-08 §6, access: lead, pastoral
 * team — `safeguarding.vulnerable.manage`). Not inverted-access —
 * unlike a `SafeguardingCase`, the spec's own Appendix A visibility
 * matrix does not list this register, and its own screen-access
 * column names a role ("lead, pastoral team"), not a per-entry grant.
 */
#[Title('Vulnerable learner register')]
#[Layout('layouts.app')]
final class Vulnerable extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $studentId = null;

    public string $vulnerabilityType = 'bereavement';

    public ?string $supportPlan = null;

    public ?int $assignedMentorId = null;

    public int $reviewFrequencyDays = 30;

    public ?int $reviewingRegistrationId = null;

    public ?string $updatedSupportPlan = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('safeguarding.vulnerable.manage');
    }

    public function add(): void
    {
        $this->validate([
            'studentId' => ['required', 'integer'],
            'vulnerabilityType' => ['required', 'string'],
        ]);

        app(AddToVulnerableLearnerRegisterAction::class)->execute(new AddToVulnerableLearnerRegisterData(
            schoolId: $this->school->id,
            studentId: (int) $this->studentId,
            vulnerabilityType: $this->vulnerabilityType,
            identifiedAt: Carbon::now(),
            identifiedByUserId: (int) Auth::id(),
            supportPlan: $this->supportPlan,
            assignedMentorId: $this->assignedMentorId,
            reviewFrequencyDays: $this->reviewFrequencyDays,
        ));

        $this->reset(['supportPlan', 'assignedMentorId']);
        $this->toast(__('Added to the register.'));
    }

    public function review(int $registrationId): void
    {
        app(ReviewVulnerableLearnerAction::class)->execute($registrationId, $this->updatedSupportPlan);

        $this->reset(['reviewingRegistrationId', 'updatedSupportPlan']);
        $this->toast(__('Review recorded — next review date updated.'));
    }

    public function render(): View
    {
        return view('welfare::safeguarding.vulnerable', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(['id', 'first_name', 'last_name']),
            'registrations' => VulnerableLearnerRegistration::where('school_id', $this->school->id)
                ->where('status', 'active')
                ->with('student:id,first_name,last_name')
                ->orderBy('next_review_on')
                ->get(),
        ]);
    }
}
