<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateConsentTypeData;
use Modules\Compliance\Models\ConsentType;
use Modules\Core\Domain\Actions\Action;

final class CreateConsentTypeAction extends Action
{
    public function execute(CreateConsentTypeData $data): ConsentType
    {
        return $this->transaction(fn (): ConsentType => ConsentType::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'description' => $data->description,
            'lawful_basis' => $data->lawfulBasis,
            'is_withdrawable' => $data->isWithdrawable,
            'required_for_enrolment' => $data->requiredForEnrolment,
            'applies_to' => $data->appliesTo,
            'renewal_frequency_months' => $data->renewalFrequencyMonths,
            'is_active' => true,
        ]));
    }
}
