<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AdministerMedicationData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $medicationName,
        public string $dose,
        public string $route,
        public CarbonInterface $administeredAt,
        public int $administeredByUserId,
        public ?int $prescriptionId = null,
        public ?int $admissionId = null,
        public ?int $clinicStockId = null,
        public float $stockQuantityConsumed = 1.0,
        public ?int $witnessedByUserId = null,
        public string $outcome = 'given',
        public ?string $omissionReason = null,
        public ?string $batchNumber = null,
        public ?CarbonInterface $expiryDate = null,
        public ?string $adverseReaction = null,
        public ?string $notes = null,
        public bool $emergencyProvision = false,
        public ?int $emergencyDecisionMakerUserId = null,
    ) {}
}
