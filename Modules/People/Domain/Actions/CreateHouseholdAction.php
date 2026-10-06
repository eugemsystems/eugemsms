<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateHouseholdData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Household;

/**
 * ACT-CreateHousehold (Book C PPL-03 §3/BR-PPL-03-016). A family grouping used
 * for sibling discounts and combined statements.
 */
final class CreateHouseholdAction extends Action
{
    public function execute(CreateHouseholdData $data): Household
    {
        if (trim($data->name) === '') {
            throw new InvalidArgumentException('A household needs a name.');
        }

        if ($data->headGuardianId !== null && ! Guardian::query()->whereKey($data->headGuardianId)->exists()) {
            throw new InvalidArgumentException('That guardian does not belong to this school.');
        }

        return $this->transaction(fn (): Household => Household::create([
            'school_id' => $data->schoolId,
            'name' => trim($data->name),
            'head_guardian_id' => $data->headGuardianId,
            'address_line_1' => $data->addressLine1,
            'city' => $data->city,
            'combined_statement' => $data->combinedStatement,
            'sibling_discount_eligible' => $data->siblingDiscountEligible,
        ]));
    }
}
