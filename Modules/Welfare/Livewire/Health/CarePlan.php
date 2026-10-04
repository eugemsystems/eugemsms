<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\ApproveCarePlanAction;
use Modules\Welfare\Domain\Actions\CreateEmergencyCarePlanAction;
use Modules\Welfare\Domain\DataObjects\CreateEmergencyCarePlanData;
use Modules\Welfare\Models\EmergencyCarePlan;
use Modules\Welfare\Models\MedicalCondition;

/**
 * `Health\CarePlan` (Book G BRD-06 §5/BR-BRD-06-007, `health.actionable.view`
 * to view — Tier 2, deliberately plain-language and readable by any
 * staff member with care responsibility; `health.clinical.manage` to
 * create/approve). The create form has no diagnosis field — the DTO
 * itself has nowhere to put one, matching BR-BRD-06-007.
 */
#[Title('Emergency care plans')]
#[Layout('layouts.app')]
final class CarePlan extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public ?int $conditionId = null;

    public string $title = '';

    public string $triggerSigns = '';

    public string $immediateActions = '';

    public ?string $medicationLocation = null;

    public ?string $medicationName = null;

    public ?string $doNotDo = null;

    public string $whoToCall = '';

    public ?string $reviewDueOn = null;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('health.actionable.view');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
    }

    public function create(): void
    {
        $this->authorizePermission('health.clinical.manage');

        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'triggerSigns' => ['required', 'string'],
            'immediateActions' => ['required', 'string'],
            'whoToCall' => ['required', 'string', 'max:255'],
        ]);

        app(CreateEmergencyCarePlanAction::class)->execute(new CreateEmergencyCarePlanData(
            schoolId: $this->school->id,
            studentId: $this->student->id,
            title: $this->title,
            triggerSigns: $this->triggerSigns,
            immediateActions: $this->immediateActions,
            whoToCall: $this->whoToCall,
            conditionId: $this->conditionId,
            medicationLocation: $this->medicationLocation,
            medicationName: $this->medicationName,
            doNotDo: $this->doNotDo,
            reviewDueOn: $this->reviewDueOn !== null && $this->reviewDueOn !== '' ? $this->reviewDueOn : null,
        ));

        $this->reset(['title', 'triggerSigns', 'immediateActions', 'medicationLocation', 'medicationName', 'doNotDo', 'whoToCall', 'reviewDueOn']);
        $this->toast(__('Care plan created — awaiting nurse approval.'));
    }

    public function approve(int $planId): void
    {
        $this->authorizePermission('health.clinical.manage');

        app(ApproveCarePlanAction::class)->execute($planId, (int) Auth::id());

        $this->toast(__('Care plan approved.'));
    }

    public function render(): View
    {
        return view('welfare::health.care-plan', [
            'plans' => EmergencyCarePlan::where('student_id', $this->student->id)->orderByDesc('id')->get(),
            // Tier 2 screen — only the public_summary ever reaches the
            // view; MedicalCondition.name/diagnosis_notes are Tier 3 and
            // are never selected here (BR-BRD-06-001).
            'conditions' => MedicalCondition::where('student_id', $this->student->id)->where('status', 'active')->get(['id', 'public_summary']),
        ]);
    }
}
