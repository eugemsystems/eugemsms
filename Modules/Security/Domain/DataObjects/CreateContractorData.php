<?php

declare(strict_types=1);

namespace Modules\Security\Domain\DataObjects;

final readonly class CreateContractorData
{
    public function __construct(
        public int $schoolId,
        public string $companyName,
        public ?int $supplierId = null,
        public ?string $contactPerson = null,
        public ?string $phone = null,
        public ?string $workType = null,
    ) {}
}
