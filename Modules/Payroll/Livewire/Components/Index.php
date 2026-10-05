<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Components;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Payroll\Domain\Actions\CreatePayComponentAction;
use Modules\Payroll\Domain\DataObjects\CreatePayComponentData;
use Modules\Payroll\Models\PayComponent;

/**
 * `Payroll\Components\Index` (Book H3 PPL-05 §2/§3 ⭐/§6,
 * `payroll.manage`). List + create, no `UpdatePayComponentAction`
 * exists. The tax-treatment flags set here (`is_taxable`,
 * `is_pensionable`, `is_nec_applicable`, `is_zimdef_applicable`,
 * `taxable_percent`) are exactly what `PayComponentResolver` reads to
 * build every statutory base in `ComputePayslipAction` — getting
 * these right here is the whole point of this screen, per
 * `CreatePayComponentAction`'s own docblock.
 */
#[Title('Pay components')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $componentType = 'earning';

    public string $category = 'allowance';

    public string $calculationMethod = 'fixed';

    public string $defaultAmountMinor = '';

    public string $defaultPercent = '';

    public string $currency = '';

    public bool $isTaxable = true;

    public bool $isPensionable = true;

    public bool $isNecApplicable = true;

    public bool $isZimdefApplicable = true;

    public string $taxablePercent = '100';

    public ?int $expenseAccountId = null;

    public ?int $liabilityAccountId = null;

    public string $costCentreSource = 'staff';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.view');
    }

    public function create(): void
    {
        $this->authorizePermission('payroll.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'componentType' => ['required', 'in:earning,deduction,employer_contribution'],
            'category' => ['required', 'in:basic,allowance,overtime,bonus,statutory,loan,third_party,benefit_in_kind'],
            // Only `fixed` and `percentage_of_basic` are actually supported
            // by `PayComponentResolver` this pass (see its own docblock) —
            // the other four spec-named methods throw
            // `UnsupportedCalculationMethodException` by name, so the
            // dropdown below offers only the two real ones rather than
            // letting a bursar configure a component that would fail at
            // compute time.
            'calculationMethod' => ['required', 'in:fixed,percentage_of_basic'],
        ]);

        app(CreatePayComponentAction::class)->execute(new CreatePayComponentData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            componentType: $this->componentType,
            category: $this->category,
            calculationMethod: $this->calculationMethod,
            defaultAmountMinor: $this->defaultAmountMinor !== '' ? (int) $this->defaultAmountMinor : null,
            defaultPercent: $this->defaultPercent !== '' ? $this->defaultPercent : null,
            currency: $this->currency !== '' ? $this->currency : null,
            isTaxable: $this->isTaxable,
            isPensionable: $this->isPensionable,
            isNecApplicable: $this->isNecApplicable,
            isZimdefApplicable: $this->isZimdefApplicable,
            taxablePercent: $this->taxablePercent,
            expenseAccountId: $this->expenseAccountId,
            liabilityAccountId: $this->liabilityAccountId,
            costCentreSource: $this->costCentreSource,
        ));

        $this->reset(['code', 'name', 'defaultAmountMinor', 'defaultPercent', 'currency']);
        $this->toast(__('Pay component created.'));
    }

    public function render(): View
    {
        return view('payroll::components.index', [
            'components' => PayComponent::where('school_id', $this->school->id)->orderBy('sort_order')->orderBy('code')->get(),
            'accounts' => Account::where('school_id', $this->school->id)->where('is_postable', true)->orderBy('code')->get(),
        ]);
    }
}
