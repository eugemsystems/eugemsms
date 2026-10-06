<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\Events\GuardianCreated;
use Modules\People\Domain\Support\PhoneNumberNormaliser;
use Modules\People\Models\Guardian;

/**
 * ACT-CreateGuardian (Book C PPL-03 §3/§11).
 */
final class CreateGuardianAction extends Action
{
    public function execute(CreateGuardianData $data): Guardian
    {
        if (! in_array($data->guardianType, ['individual', 'organisation'], true)) {
            throw new InvalidArgumentException("Unknown guardian type [{$data->guardianType}].");
        }

        if ($data->guardianType === 'individual' && (trim((string) $data->firstName) === '' || trim((string) $data->lastName) === '')) {
            throw new InvalidArgumentException('An individual guardian needs a first and last name.');
        }

        if ($data->guardianType === 'organisation' && trim((string) $data->organisationName) === '') {
            throw new InvalidArgumentException('An organisation guardian needs a name.');
        }

        if ($data->email !== null && $data->email !== '' && filter_var($data->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('That email address is not valid.');
        }

        $phone = $data->primaryPhone === null || trim($data->primaryPhone) === '' ? null : PhoneNumberNormaliser::normalise($data->primaryPhone);

        return $this->transaction(function () use ($data, $phone): Guardian {
            $guardian = Guardian::create([
                'school_id' => $data->schoolId,
                'guardian_type' => $data->guardianType,
                'title' => $data->title,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'organisation_name' => $data->organisationName,
                'organisation_type' => $data->organisationType,
                'primary_phone' => $phone,
                'email' => $data->email,
                'created_by' => $data->createdByUserId,
            ]);

            event(new GuardianCreated($guardian));

            return $guardian;
        });
    }
}
