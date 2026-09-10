<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

final readonly class WithdrawConsentData
{
    public function __construct(
        public int $consentId,
        public int $withdrawnByUserId,
        public string $withdrawalReason,
    ) {}
}
