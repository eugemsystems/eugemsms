<?php

declare(strict_types=1);

namespace Modules\Fiscal\Livewire\Rules;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Fiscal\Domain\Actions\CreateFiscalisationRuleAction;
use Modules\Fiscal\Domain\DataObjects\CreateFiscalisationRuleData;
use Modules\Fiscal\Models\FiscalisationRule;

/**
 * `Fiscal\Rules\Index` (Book H3 FIN-13 §4/§7 ⭐, `fiscal.rules.manage`).
 * The routing engine itself — "getting the routing wrong either
 * under-reports... or over-reports... fiscalising exempt tuition,
 * which is worse" (§2). Every rule requires a rationale and a
 * reviewing accountant at creation, exactly as `CreateFiscalisationRuleAction`
 * requires (BR-FIN-13-002) — there is no "create without review" path
 * here to accidentally offer.
 */
#[Title('Fiscalisation rules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $ruleName = '';

    public string $sourceType = 'wallet_product';

    public string $sourceIdentifier = '';

    public bool $isFiscalisable = true;

    public string $taxType = 'standard';

    public string $taxRatePercent = '0';

    public string $taxCode = '';

    public string $rationale = '';

    public string $priority = '100';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('fiscal.rules.manage');
    }

    public function create(): void
    {
        $this->validate([
            'ruleName' => ['required', 'string', 'max:150'],
            'sourceType' => ['required', 'in:fee_component,wallet_product,farm_sale,facility_hire,uniform_sale'],
            'taxType' => ['required', 'in:standard,zero_rated,exempt,withholding'],
            'rationale' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
            schoolId: $this->school->id,
            ruleName: $this->ruleName,
            sourceType: $this->sourceType,
            isFiscalisable: $this->isFiscalisable,
            taxType: $this->taxType,
            rationale: $this->rationale,
            reviewedByUserId: (int) Auth::id(),
            sourceIdentifier: $this->sourceIdentifier !== '' ? $this->sourceIdentifier : null,
            taxRatePercent: $this->taxRatePercent,
            taxCode: $this->taxCode !== '' ? $this->taxCode : null,
            priority: (int) $this->priority,
        ));

        $this->reset(['ruleName', 'sourceIdentifier', 'taxCode', 'rationale']);
        $this->toast(__('Rule created and reviewed.'));
    }

    public function render(): View
    {
        return view('fiscal::rules.index', [
            'rules' => FiscalisationRule::where('school_id', $this->school->id)->orderBy('source_type')->orderBy('priority')->get(),
        ]);
    }
}
