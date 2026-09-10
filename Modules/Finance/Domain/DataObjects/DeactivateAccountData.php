<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class DeactivateAccountData
{
    public function __construct(
        public int $accountId,
        public int $deactivatedByUserId,
    ) {}
}
