<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\UpdateBankAccountData;
use Modules\Finance\Models\BankAccount;

final class UpdateBankAccountAction extends Action
{
    public function execute(UpdateBankAccountData $data): BankAccount
    {
        $account = BankAccount::findOrFail($data->bankAccountId);

        return $this->transaction(function () use ($account, $data): BankAccount {
            $account->update([
                'gl_account_id' => $data->glAccountId,
                'bank_name' => $data->bankName,
                'account_name' => $data->accountName,
                'account_number' => $data->accountNumber,
                'branch' => $data->branch,
                'currency' => $data->currency,
                'account_type' => $data->accountType,
                'is_active' => $data->isActive,
            ]);

            return $account->fresh();
        });
    }
}
