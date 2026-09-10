<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateConsentTypeData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $description,
        public string $lawfulBasis,
        public string $appliesTo,
        public bool $isWithdrawable = true,
        public bool $requiredForEnrolment = false,
        public ?int $renewalFrequencyMonths = null,
    ) {}
}
