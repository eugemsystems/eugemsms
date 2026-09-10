<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class InitiatePaymentData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $gatewayId,
        public string $idempotencyKey,
        public string $payerName,
        public string $purpose,
        public int $amountMinor,
        public string $currency,
        public ?int $studentId = null,
        public ?int $payerUserId = null,
        public ?string $payerPhone = null,
        public ?string $payerEmail = null,
        public ?string $method = null,
    ) {}
}
