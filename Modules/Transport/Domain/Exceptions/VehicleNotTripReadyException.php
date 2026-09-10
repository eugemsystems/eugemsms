<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-01 §4/BR-OPS-01-001/004/AC-OPS-01-001. The message
 * always names the specific expired item — never a generic
 * "not roadworthy" refusal.
 */
class VehicleNotTripReadyException extends DomainException
{
    public static function expiredCompliance(int $vehicleId, string $complianceType): self
    {
        return new self(
            "Vehicle #{$vehicleId} has an expired {$complianceType} and cannot be assigned to a trip (BR-OPS-01-001).",
            ['vehicle_id' => $vehicleId, 'compliance_type' => $complianceType],
        );
    }

    public static function expiredDriverDocument(int $driverId, string $documentType): self
    {
        return new self(
            "Driver #{$driverId} has an expired {$documentType} and cannot be assigned to a trip (BR-OPS-01-004).",
            ['driver_id' => $driverId, 'document_type' => $documentType],
        );
    }

    public function errorCode(): string
    {
        return 'VEHICLE_NOT_TRIP_READY';
    }
}
