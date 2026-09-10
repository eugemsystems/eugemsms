<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateStatutoryDocumentData
{
    public function __construct(
        public int $schoolId,
        public string $documentType,
        public string $issuingAuthority,
        public ?string $referenceNumber = null,
        public ?string $issuedOn = null,
        public ?string $expiresOn = null,
        public ?int $fileId = null,
        public int $renewalLeadDays = 60,
        public ?int $responsibleStaffId = null,
    ) {}
}
