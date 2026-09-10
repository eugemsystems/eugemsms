<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Finance\Domain\DataObjects\CreateCostCentreData;
use Modules\Finance\Models\CostCentre;

final class CreateCostCentreAction extends Action
{
    public function execute(CreateCostCentreData $data): CostCentre
    {
        $exists = CostCentre::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->where('code', $data->code)
            ->exists();

        if ($exists) {
            throw new DuplicateRecordException(
                "Cost centre code [{$data->code}] already exists for this school.",
                ['code' => $data->code],
            );
        }

        return $this->transaction(fn (): CostCentre => CostCentre::create([
            'school_id' => $data->schoolId,
            'parent_id' => $data->parentId,
            'code' => $data->code,
            'name' => $data->name,
            'section_id' => $data->sectionId,
            'manager_user_id' => $data->managerUserId,
            'is_profit_centre' => $data->isProfitCentre,
            'is_active' => true,
        ]));
    }
}
