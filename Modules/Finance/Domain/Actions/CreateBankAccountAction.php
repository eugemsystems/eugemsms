<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateBankAccountData;
use Modules\Finance\Models\BankAccount;

/**
 * ACT-CreateBankAccount (Book B FIN-05 §3, `finance.bank.manage`). No
 * Action existed for `bank_accounts` at all — the model/table were
 * built (see `.ai/rules/finance.md`), nothing wrote to them yet.
 */
final class CreateBankAccountAction extends Action
{
    public function execute(CreateBankAccountData $data): BankAccount
    {
        return $this->transaction(fn (): BankAccount => BankAccount::create([
            'school_id' => $data->schoolId,
            'gl_account_id' => $data->glAccountId,
            'bank_name' => $data->bankName,
            'account_name' => $data->accountName,
            'account_number' => $data->accountNumber,
            'branch' => $data->branch,
            'currency' => $data->currency,
            'account_type' => $data->accountType,
            'is_active' => $data->isActive,
        ]));
    }
}
