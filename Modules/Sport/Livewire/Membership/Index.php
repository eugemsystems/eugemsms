<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Membership;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Sport\Domain\Actions\JoinActivityAction;
use Modules\Sport\Domain\Actions\WithdrawFromActivityAction;
use Modules\Sport\Domain\DataObjects\JoinActivityData;
use Modules\Sport\Domain\Exceptions\ActivityCapacityExceededException;
use Modules\Sport\Domain\Exceptions\GuardianConsentRequiredException;
use Modules\Sport\Domain\Exceptions\MedicalClearanceRequiredException;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\ActivityMembership;

/**
 * `Membership\Index` (Book H2 OPS-07 §4 ⭐/BR-OPS-07-001/002/003/004,
 * `activities.manage`). Consent and medical clearance status shown
 * per member — `JoinActivityAction` itself is the only gate; this
 * screen just offers the inputs it needs and surfaces its refusal
 * verbatim rather than duplicating the check.
 */
#[Title('Membership')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $activityId = null;

    public ?int $studentId = null;

    public bool $consentReceived = false;

    public bool $medicalCleared = false;

    public bool $overrideCapacity = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('activities.manage');
    }

    public function join(): void
    {
        $this->validate([
            'activityId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
        ]);

        try {
            app(JoinActivityAction::class)->execute(new JoinActivityData(
                schoolId: $this->school->id,
                academicYearId: (int) SessionContext::yearId(),
                termId: (int) SessionContext::termId(),
                activityId: (int) $this->activityId,
                studentId: (int) $this->studentId,
                raisedByUserId: (int) auth()->id(),
                consentReceived: $this->consentReceived,
                medicalCleared: $this->medicalCleared,
                overrideCapacity: $this->overrideCapacity,
            ));
        } catch (GuardianConsentRequiredException|MedicalClearanceRequiredException|ActivityCapacityExceededException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['studentId', 'consentReceived', 'medicalCleared', 'overrideCapacity']);
        $this->toast(__('Membership recorded.'));
    }

    public function withdraw(int $membershipId): void
    {
        app(WithdrawFromActivityAction::class)->execute($membershipId);
        $this->toast(__('Membership withdrawn.'));
    }

    public function render(): View
    {
        return view('sport::membership.index', [
            'memberships' => ActivityMembership::with('activity', 'student')->where('school_id', $this->school->id)->orderByDesc('id')->limit(50)->get(),
            'activities' => Activity::where('school_id', $this->school->id)->where('is_active', true)->orderBy('name')->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('last_name')->limit(200)->get(),
        ]);
    }
}
