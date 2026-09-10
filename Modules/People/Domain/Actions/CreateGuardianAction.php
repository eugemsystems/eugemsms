<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\Events\GuardianCreated;
use Modules\People\Models\Guardian;

/**
 * ACT-CreateGuardian (Book C PPL-03 §3/§11).
 */
final class CreateGuardianAction extends Action
{
    public function execute(CreateGuardianData $data): Guardian
    {
        return $this->transaction(function () use ($data): Guardian {
            $guardian = Guardian::create([
                'school_id' => $data->schoolId,
                'guardian_type' => $data->guardianType,
                'title' => $data->title,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'organisation_name' => $data->organisationName,
                'organisation_type' => $data->organisationType,
                'primary_phone' => $data->primaryPhone,
                'email' => $data->email,
                'created_by' => $data->createdByUserId,
            ]);

            event(new GuardianCreated($guardian));

            return $guardian;
        });
    }
}
