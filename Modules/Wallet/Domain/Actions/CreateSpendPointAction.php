<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Wallet\Domain\DataObjects\CreateSpendPointData;
use Modules\Wallet\Models\SpendPoint;

/**
 * ACT-CreateSpendPoint (Book H3 FIN-14 §2).
 */
final class CreateSpendPointAction extends Action
{
    public function execute(CreateSpendPointData $data): SpendPoint
    {
        return $this->transaction(fn (): SpendPoint => SpendPoint::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'point_type' => $data->pointType,
            'store_id' => $data->storeId,
            'till_id' => $data->tillId,
            'income_account_id' => $data->incomeAccountId,
            'cost_centre_id' => $data->costCentreId,
            'is_fiscalisable' => $data->isFiscalisable,
            'is_active' => true,
        ]));
    }
}
