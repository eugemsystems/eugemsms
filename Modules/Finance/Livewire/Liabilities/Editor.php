<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Liabilities;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Domain\Actions\CreateFeeLiabilityAction;
use Modules\People\Domain\Actions\DeactivateFeeLiabilityAction;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Domain\DataObjects\DeactivateFeeLiabilityData;
use Modules\People\Models\FeeLiability;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Finance\Liabilities\Editor` (Book B FIN-03 §5, `finance.liability.manage`)
 * — per learner, visual share allocation with a live total check.
 * Calls `PPL-03`'s own `CreateFeeLiabilityAction`/`DeactivateFeeLiabilityAction`
 * (Finance never gets its own copy of this table or its rules — see
 * `LiabilityResolver`'s own docblock for why PPL-03 owns it) — this
 * screen is genuinely just the UI that action's own docblock names as
 * "deferred along with that screen".
 */
#[Title('Fee liabilities')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public Student $student;

    public ?int $guardianId = null;

    public ?int $componentId = null;

    public string $shareType = 'percentage';

    public string $sharePercent = '';

    public string $shareAmount = '';

    public string $currency = '';

    public int $priority = 100;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.liability.manage');
        $this->student = $student;
    }

    public function add(): void
    {
        $this->validate([
            'guardianId' => ['required', 'integer'],
            'shareType' => ['required', 'in:percentage,fixed,full_component'],
            'sharePercent' => ['required_if:shareType,percentage', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'shareAmount' => ['required_if:shareType,fixed', 'nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'priority' => ['required', 'integer', 'min:1'],
        ]);

        app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData(
            schoolId: $this->school->id,
            studentId: $this->student->id,
            guardianId: (int) $this->guardianId,
            shareType: $this->shareType,
            createdByUserId: (int) Auth::id(),
            componentId: $this->componentId,
            sharePercent: $this->shareType === 'percentage' ? $this->sharePercent : null,
            shareAmountMinor: $this->shareType === 'fixed' ? (int) round((float) $this->shareAmount * 100) : null,
            currency: $this->currency !== '' ? $this->currency : null,
            priority: $this->priority,
        ));

        $this->reset(['guardianId', 'componentId', 'sharePercent', 'shareAmount', 'currency']);
        $this->shareType = 'percentage';
        $this->priority = 100;
        $this->toast(__('Liability rule added.'));
    }

    public function deactivate(int $feeLiabilityId): void
    {
        app(DeactivateFeeLiabilityAction::class)->execute(new DeactivateFeeLiabilityData($feeLiabilityId));
        $this->toast(__('Liability rule deactivated.'));
    }

    /**
     * The "live total check" (Book B FIN-03 §5's own phrase) — summed
     * per component (plus the `null` = "all other components" bucket),
     * so a bursar sees immediately whether a component's percentage
     * rules add up to 100% or leave a residue that falls through to
     * the default fee-responsible guardian (BR-FIN-03-007), which is a
     * valid, deliberate configuration, not an error — this is a
     * transparency aid, not a hard validation gate.
     *
     * @return array<int|string, string>
     */
    private function percentTotalsByComponent(): array
    {
        return FeeLiability::where('student_id', $this->student->id)
            ->where('is_active', true)
            ->where('share_type', 'percentage')
            ->get()
            ->groupBy(fn (FeeLiability $l) => $l->component_id ?? 'all_other')
            ->map(fn ($rules) => (string) $rules->sum('share_percent'))
            ->all();
    }

    public function render(): View
    {
        return view('finance::liabilities.editor', [
            'liabilities' => FeeLiability::where('student_id', $this->student->id)->where('is_active', true)->with('guardian', 'component')->orderBy('priority')->get(),
            'guardians' => Guardian::orderBy('first_name')->get(),
            'components' => FeeComponent::where('is_active', true)->orderBy('code')->get(),
            'percentTotals' => $this->percentTotalsByComponent(),
        ]);
    }
}
