<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CreateAccountData
{
    public function __construct(
        public int $schoolId,
        public string $accountTypeCode,
        public string $code,
        public string $name,
        public int $createdByUserId,
        public ?string $description = null,
        public ?int $parentId = null,
        public bool $isPostable = true,
        public bool $isControlAccount = false,
        public ?string $subledgerType = null,
        public bool $isSystem = false,
        public ?string $systemKey = null,
        public ?string $currency = null,
        public bool $requiresCostCentre = false,
    ) {}
}
