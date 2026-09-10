<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateIntakeData;
use Modules\People\Models\Intake;

/**
 * ACT-CreateIntake (Book C PPL-02 §2).
 */
final class CreateIntakeAction extends Action
{
    public function execute(CreateIntakeData $data): Intake
    {
        return $this->transaction(fn (): Intake => Intake::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'name' => $data->name,
            'grade_level_id' => $data->gradeLevelId,
            'opens_on' => $data->opensOn->toDateString(),
            'closes_on' => $data->closesOn->toDateString(),
            'target_places' => $data->targetPlaces,
            'application_fee_minor' => $data->applicationFeeMinor,
            'application_fee_currency' => $data->applicationFeeCurrency,
            'acceptance_deposit_minor' => $data->acceptanceDepositMinor,
            'acceptance_deposit_currency' => $data->acceptanceDepositCurrency,
            'deposit_deadline_days' => $data->depositDeadlineDays,
            'status' => 'open',
            'created_by' => $data->createdByUserId,
        ]));
    }
}
