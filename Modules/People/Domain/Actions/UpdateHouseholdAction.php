<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\UpdateHouseholdData;
use Modules\People\Models\Guardian;
use Modules\People\Models\Household;

/**
 * ACT-UpdateHousehold (Book C PPL-03 §3/BR-PPL-03-017). Changing whether a
 * household counts for sibling discounts takes effect on the next evaluation.
 */
final class UpdateHouseholdAction extends Action
{
    public function execute(UpdateHouseholdData $data): Household
    {
        $household = Household::findOrFail($data->householdId);

        if (trim($data->name) === '') {
            throw new InvalidArgumentException('A household needs a name.');
        }

        if ($data->headGuardianId !== null && ! Guardian::query()->whereKey($data->headGuardianId)->exists()) {
            throw new InvalidArgumentException('That guardian does not belong to this school.');
        }

        return $this->transaction(function () use ($household, $data): Household {
            $household->update([
                'name' => trim($data->name),
                'head_guardian_id' => $data->headGuardianId,
                'address_line_1' => $data->addressLine1,
                'city' => $data->city,
                'combined_statement' => $data->combinedStatement,
                'sibling_discount_eligible' => $data->siblingDiscountEligible,
            ]);

            return $household->fresh();
        });
    }
}
