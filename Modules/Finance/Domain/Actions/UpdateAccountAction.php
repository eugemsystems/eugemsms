<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\UpdateAccountData;
use Modules\Finance\Models\Account;

final class UpdateAccountAction extends Action
{
    public function execute(UpdateAccountData $data): Account
    {
        $account = Account::findOrFail($data->accountId);

        return $this->transaction(function () use ($account, $data): Account {
            $account->update(array_filter([
                'name' => $data->name,
                'description' => $data->description,
                'is_postable' => $data->isPostable,
                'requires_cost_centre' => $data->requiresCostCentre,
                'updated_by' => $data->updatedByUserId,
            ], fn ($value): bool => $value !== null));

            return $account;
        });
    }
}
