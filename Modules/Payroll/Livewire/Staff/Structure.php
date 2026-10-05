<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Staff;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\AddStaffPayComponentAction;
use Modules\Payroll\Domain\Actions\CreateStaffPayStructureAction;
use Modules\Payroll\Domain\DataObjects\AddStaffPayComponentData;
use Modules\Payroll\Domain\DataObjects\CreateStaffPayStructureData;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayGrade;
use Modules\Payroll\Models\StaffPayStructure;
use Modules\People\Models\Staff;

/**
 * `Payroll\Staff\Structure` (Book H3 PPL-05 §2 ⭐/§6,
 * `payroll.staff.manage`). Dated — creating a new structure for a
 * staff member who already has an `active` one supersedes it rather
 * than editing it (`CreateStaffPayStructureAction`'s own docblock);
 * an already-computed payslip is unaffected either way, since it
 * stored its own amounts at compute time. Also hosts
 * `AddStaffPayComponentAction` for the selected structure, since the
 * spec names no separate screen for attaching individual components.
 *
 * Deliberately simplified against BR-PPL-05-010: split-currency
 * salaries (`usd_portion_percent`/`zwg_portion_percent`) are not
 * exposed here because `ComputePayslipAction` itself does not support
 * them yet (its own docblock) — only a single `payment_currency` per
 * structure is offered.
 */
#[Title('Staff pay structure')]
#[Layout('layouts.app')]
final class Structure extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $staffId = null;

    public ?int $gradeId = null;

    public string $notch = '';

    public string $primaryCurrency = 'USD';

    public string $paymentCurrency = 'USD';

    public string $effectiveFrom = '';

    public ?int $selectedStructureId = null;

    public ?int $componentId = null;

    public string $componentAmountMinor = '';

    public string $componentPercent = '';

    public string $componentCurrency = 'USD';

    public string $componentEffectiveFrom = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.staff.manage', PermissionScope::Own);

        $this->effectiveFrom = now()->toDateString();
        $this->componentEffectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->validate([
            'staffId' => ['required', 'integer'],
            'primaryCurrency' => ['required', 'in:USD,ZWG'],
            'paymentCurrency' => ['required', 'in:USD,ZWG'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        $structure = app(CreateStaffPayStructureAction::class)->execute(new CreateStaffPayStructureData(
            schoolId: $this->school->id,
            staffId: (int) $this->staffId,
            primaryCurrency: $this->primaryCurrency,
            paymentCurrency: $this->paymentCurrency,
            effectiveFrom: Carbon::parse($this->effectiveFrom),
            approvedByUserId: (int) auth()->id(),
            gradeId: $this->gradeId,
            notch: $this->notch !== '' ? $this->notch : null,
        ));

        $this->selectedStructureId = $structure->id;
        $this->toast(__('Pay structure created — any prior active structure was superseded.'));
    }

    public function selectStructure(int $structureId): void
    {
        $this->selectedStructureId = $structureId;
    }

    public function addComponent(): void
    {
        $this->validate([
            'selectedStructureId' => ['required', 'integer'],
            'componentId' => ['required', 'integer'],
            'componentCurrency' => ['required', 'in:USD,ZWG'],
            'componentEffectiveFrom' => ['required', 'date'],
        ]);

        app(AddStaffPayComponentAction::class)->execute(new AddStaffPayComponentData(
            schoolId: $this->school->id,
            payStructureId: (int) $this->selectedStructureId,
            componentId: (int) $this->componentId,
            currency: $this->componentCurrency,
            effectiveFrom: Carbon::parse($this->componentEffectiveFrom),
            amountMinor: $this->componentAmountMinor !== '' ? (int) $this->componentAmountMinor : null,
            percent: $this->componentPercent !== '' ? $this->componentPercent : null,
        ));

        $this->reset(['componentAmountMinor', 'componentPercent']);
        $this->toast(__('Component added to structure.'));
    }

    public function render(): View
    {
        return view('payroll::staff.structure', [
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'grades' => PayGrade::where('school_id', $this->school->id)->where('is_active', true)->orderBy('code')->get(),
            'structures' => StaffPayStructure::where('school_id', $this->school->id)->with('components.component')->orderByDesc('effective_from')->limit(50)->get(),
            'components' => PayComponent::where('school_id', $this->school->id)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }
}
