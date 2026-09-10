<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\CreateUtilityAccountData;
use Modules\Utilities\Models\UtilityAccount;

/**
 * ACT-CreateUtilityAccount (Book H2 OPS-04 §2).
 */
final class CreateUtilityAccountAction extends Action
{
    public function execute(CreateUtilityAccountData $data): UtilityAccount
    {
        return $this->transaction(fn (): UtilityAccount => UtilityAccount::create([
            'school_id' => $data->schoolId,
            'utility_type' => $data->utilityType,
            'provider' => $data->provider,
            'account_number' => $data->accountNumber,
            'tariff_code' => $data->tariffCode,
            'billing_mode' => $data->billingMode,
            'cost_centre_id' => $data->costCentreId,
            'expense_account_id' => $data->expenseAccountId,
            'is_active' => true,
        ]));
    }
}
