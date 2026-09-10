<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\CreateDriverData;
use Modules\Transport\Models\Driver;

/**
 * ACT-CreateDriver (Book H2 OPS-01 §2).
 */
final class CreateDriverAction extends Action
{
    public function execute(CreateDriverData $data): Driver
    {
        return $this->transaction(fn (): Driver => Driver::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'licence_number' => $data->licenceNumber,
            'licence_classes' => $data->licenceClasses,
            'licence_expires_on' => $data->licenceExpiresOn->toDateString(),
            'defensive_driving_cert' => $data->defensiveDrivingCert,
            'defensive_expires_on' => $data->defensiveExpiresOn?->toDateString(),
            'medical_certificate_on' => $data->medicalCertificateOn?->toDateString(),
            'medical_expires_on' => $data->medicalExpiresOn?->toDateString(),
            'years_experience' => $data->yearsExperience,
            'status' => 'active',
            'incident_count' => 0,
        ]));
    }
}
