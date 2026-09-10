<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class CreateContractData
{
    public function __construct(
        public int $schoolId,
        public string $counterpartyName,
        public string $contractType,
        public string $startsOn,
        public ?string $description = null,
        public ?string $expiresOn = null,
        public int $renewalLeadDays = 60,
        public ?int $documentFileId = null,
        public ?int $responsibleStaffId = null,
    ) {}
}
