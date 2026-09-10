<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class CreateStudentWalletData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $currency,
        public int $liabilityAccountId,
    ) {}
}
