<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

final readonly class CreateSupplierData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $supplierType,
        public string $preferredCurrency,
        public int $createdByUserId,
        public ?string $tradingName = null,
        public ?string $vatNumber = null,
        public bool $isVatRegistered = false,
        public ?string $bpNumber = null,
        public ?string $companyRegistration = null,
        public ?string $contactPerson = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?int $controlAccountId = null,
        public int $paymentTermsDays = 30,
    ) {}
}
