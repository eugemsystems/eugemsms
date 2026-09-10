<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AdmitToSickBayData
{
    /**
     * @param  array<string, mixed>|null  $initialObservations
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public CarbonInterface $admittedAt,
        public int $admittedByUserId,
        public string $presentingComplaint,
        public string $severity,
        public ?array $initialObservations = null,
        public ?string $bedReference = null,
        public bool $isIsolation = false,
        public ?string $isolationReason = null,
        public ?CarbonInterface $expectedDischargeAt = null,
    ) {}
}
