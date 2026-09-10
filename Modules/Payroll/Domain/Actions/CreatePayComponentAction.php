<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreatePayComponentData;
use Modules\Payroll\Models\PayComponent;

/**
 * ACT-CreatePayComponent (Book H3 PPL-05 §2/§3 ⭐). The tax-treatment
 * flags set here are what `PayComponentResolver` reads to build every
 * statutory base — get them right here, not in the calculation code.
 */
final class CreatePayComponentAction extends Action
{
    public function execute(CreatePayComponentData $data): PayComponent
    {
        return $this->transaction(fn (): PayComponent => PayComponent::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'component_type' => $data->componentType,
            'category' => $data->category,
            'calculation_method' => $data->calculationMethod,
            'default_amount_minor' => $data->defaultAmountMinor,
            'default_percent' => $data->defaultPercent,
            'currency' => $data->currency,
            'is_taxable' => $data->isTaxable,
            'is_pensionable' => $data->isPensionable,
            'is_nec_applicable' => $data->isNecApplicable,
            'is_zimdef_applicable' => $data->isZimdefApplicable,
            'taxable_percent' => $data->taxablePercent,
            'expense_account_id' => $data->expenseAccountId,
            'liability_account_id' => $data->liabilityAccountId,
            'cost_centre_source' => $data->costCentreSource,
            'appears_on_payslip' => true,
            'is_active' => true,
        ]));
    }
}
