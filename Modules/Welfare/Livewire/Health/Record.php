<?php

declare(strict_types=1);

namespace Modules\Welfare\Livewire\Health;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Welfare\Domain\Actions\DeclareMedicalConditionAction;
use Modules\Welfare\Domain\Actions\ResolveMedicalTierAction;
use Modules\Welfare\Domain\Actions\VerifyMedicalConditionAction;
use Modules\Welfare\Domain\DataObjects\DeclareMedicalConditionData;
use Modules\Welfare\Domain\Support\MedicalTier;
use Modules\Welfare\Models\MedicalCondition;
use Modules\Welfare\Models\MedicalRecord;

/**
 * `Health\Record` (Book G BRD-06 §5, `health.clinical.view` — Tier 3).
 * Folds the spec's separate "Clinical record" and "Condition register"
 * screens into one, like `Curriculum\Frameworks`'s own precedent.
 *
 * Tier is resolved through `ResolveMedicalTierAction`, never assumed
 * from the permission alone — a viewer who only clears Tier 2 (care
 * responsibility, no `health.clinical.view`) is refused here rather
 * than silently shown a narrower view, because this screen's own job
 * is specifically the Tier 3 detail; Tier 2 staff have `Health\CarePlan`
 * and `Health\Alerts` instead. Every Tier 3 resolution the Action
 * performs writes to `data_access_log` (AC-BRD-06-002) — this screen
 * never queries `MedicalCondition`/`MedicalRecord` fields before that
 * call succeeds.
 */
#[Title('Clinical record')]
#[Layout('layouts.app')]
final class Record extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public string $conditionType = 'allergy';

    public string $conditionName = '';

    public string $severity = 'mild';

    public ?string $category = null;

    public ?string $publicSummary = null;

    public bool $requiresEmergencyPlan = false;

    public bool $affectsDietary = false;

    public bool $affectsPhysicalActivity = false;

    public bool $affectsAccommodation = false;

    public ?string $accommodationRequirement = null;

    public ?string $diagnosisNotes = null;

    public string $declaredBy = 'nurse';

    public string $effectiveFrom = '';

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->effectiveFrom = now()->toDateString();

        $this->resolveTierOrAbort();
    }

    public function declare(): void
    {
        $this->authorizePermission('health.clinical.manage');

        $this->validate([
            'conditionName' => ['required', 'string', 'max:150'],
            'severity' => ['required', 'string'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        if ($this->affectsDietary && ($this->publicSummary === null || trim($this->publicSummary) === '')) {
            $this->toast(__('A dietary-affecting condition requires a public summary — never the clinical detail.'), 'danger');

            return;
        }

        app(DeclareMedicalConditionAction::class)->execute(new DeclareMedicalConditionData(
            schoolId: $this->school->id,
            studentId: $this->student->id,
            conditionType: $this->conditionType,
            name: $this->conditionName,
            severity: $this->severity,
            declaredBy: $this->declaredBy,
            effectiveFrom: Carbon::parse($this->effectiveFrom),
            category: $this->category,
            publicSummary: $this->publicSummary,
            requiresEmergencyPlan: $this->requiresEmergencyPlan,
            affectsDietary: $this->affectsDietary,
            affectsPhysicalActivity: $this->affectsPhysicalActivity,
            affectsAccommodation: $this->affectsAccommodation,
            accommodationRequirement: $this->accommodationRequirement,
            diagnosisNotes: $this->diagnosisNotes,
            createdByUserId: (int) Auth::id(),
        ));

        $this->reset(['conditionName', 'publicSummary', 'accommodationRequirement', 'diagnosisNotes']);
        $this->toast(__('Condition declared.'));
    }

    public function verify(int $conditionId): void
    {
        $this->authorizePermission('health.clinical.manage');

        app(VerifyMedicalConditionAction::class)->execute($conditionId, (int) Auth::id());

        $this->toast(__('Condition verified.'));
    }

    /**
     * Enforces the real policy, not only the permission flag — a Tier
     * 2-only viewer (care responsibility, no `health.clinical.view`) is
     * refused this screen outright rather than shown a degraded view.
     */
    private function resolveTierOrAbort(): void
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        try {
            $tier = app(ResolveMedicalTierAction::class)->execute($user, $this->student, RequestFacade::ip());
        } catch (InsufficientScopeException) {
            abort(403);
        }

        abort_unless($tier === MedicalTier::Clinical, 403);
    }

    public function render(): View
    {
        return view('welfare::health.record', [
            'record' => MedicalRecord::where('student_id', $this->student->id)->first(),
            'conditions' => MedicalCondition::where('student_id', $this->student->id)->orderByDesc('effective_from')->get(),
        ]);
    }
}
