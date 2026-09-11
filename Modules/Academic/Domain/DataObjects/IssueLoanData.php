<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class IssueLoanData
{
    public function __construct(
        public int $termId,
        public int $copyId,
        public string $borrowerType,
        public int $borrowerId,
        public string $borrowerCategory,
        public int $issuedByUserId,
    ) {}
}
