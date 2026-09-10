<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Transport\Domain\DataObjects\RegisterVehicleComplianceData;
use Modules\Transport\Models\VehicleCompliance;

/**
 * ACT-RegisterVehicleCompliance (Book H2 OPS-01 §2 🇿🇼/BR-OPS-01-002).
 * Each compliance type tracks independently — a renewed
 * certificate-of-fitness is a NEW row, not an overwrite of the old
 * one, so the expiry history stays intact.
 */
final class RegisterVehicleComplianceAction extends Action
{
    public function execute(RegisterVehicleComplianceData $data): VehicleCompliance
    {
        $status = $data->expiresOn->lessThan(Carbon::now()) ? 'expired' : 'valid';

        return $this->transaction(fn (): VehicleCompliance => VehicleCompliance::create([
            'school_id' => $data->schoolId,
            'vehicle_id' => $data->vehicleId,
            'compliance_type' => $data->complianceType,
            'reference_number' => $data->referenceNumber,
            'issued_on' => $data->issuedOn?->toDateString(),
            'expires_on' => $data->expiresOn->toDateString(),
            'cost_minor' => $data->costMinor,
            'currency' => $data->currency,
            'issuing_authority' => $data->issuingAuthority,
            'status' => $status,
        ]));
    }
}
