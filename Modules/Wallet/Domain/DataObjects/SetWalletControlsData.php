<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\DataObjects;

final readonly class SetWalletControlsData
{
    /**
     * @param  array<int, string>|null  $blockedCategories
     */
    public function __construct(
        public int $walletId,
        public int $setByGuardianId,
        public ?int $dailyLimitMinor = null,
        public ?int $weeklyLimitMinor = null,
        public ?int $perTransactionLimitMinor = null,
        public ?array $blockedCategories = null,
        public ?int $lowBalanceThresholdMinor = null,
        public bool $autoTopupEnabled = false,
        public ?int $autoTopupAmountMinor = null,
    ) {}
}
