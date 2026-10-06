<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RecordBeneficiaryConditionData;
use Modules\People\Models\SponsorshipBeneficiary;

/**
 * ACT-RecordBeneficiaryCondition (Book C PPL-03 §6/BR-PPL-03-020). Records
 * whether a beneficiary met the sponsor's performance condition. A failed
 * condition only flags the learner for review; nothing is withdrawn here.
 */
final class RecordBeneficiaryConditionAction extends Action
{
    public function execute(RecordBeneficiaryConditionData $data): SponsorshipBeneficiary
    {
        $beneficiary = SponsorshipBeneficiary::findOrFail($data->beneficiaryId);

        if ($beneficiary->performance_condition === null) {
            throw new InvalidArgumentException('This beneficiary has no performance condition to record against.');
        }

        return $this->transaction(function () use ($beneficiary, $data): SponsorshipBeneficiary {
            $beneficiary->update(['condition_met' => $data->conditionMet]);

            return $beneficiary->fresh();
        });
    }
}
