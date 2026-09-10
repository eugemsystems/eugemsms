<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Finance\Domain\DataObjects\CreateAccountData;
use Modules\Finance\Domain\Events\AccountCreated;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountType;

final class CreateAccountAction extends Action
{
    public function execute(CreateAccountData $data): Account
    {
        $accountType = AccountType::where('code', $data->accountTypeCode)->firstOrFail();

        $exists = Account::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('code', $data->code)
            ->exists();

        if ($exists) {
            throw new DuplicateRecordException(
                "Account code [{$data->code}] already exists for this school.",
                ['code' => $data->code],
            );
        }

        return $this->transaction(function () use ($data, $accountType): Account {
            $account = Account::create([
                'school_id' => $data->schoolId,
                'parent_id' => $data->parentId,
                'account_type_id' => $accountType->id,
                'code' => $data->code,
                'name' => $data->name,
                'description' => $data->description,
                'is_postable' => $data->isPostable,
                'is_control_account' => $data->isControlAccount,
                'subledger_type' => $data->subledgerType,
                'is_system' => $data->isSystem,
                'system_key' => $data->systemKey,
                'currency' => $data->currency,
                'requires_cost_centre' => $data->requiresCostCentre,
                'is_active' => true,
                'opened_on' => now()->toDateString(),
                'created_by' => $data->createdByUserId,
            ]);

            event(new AccountCreated($account));

            return $account;
        });
    }
}
