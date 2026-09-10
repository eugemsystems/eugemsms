<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RevaluableAccountResult
{
    public function __construct(
        public int $accountId,
        public string $accountCode,
        public bool $isDebitNormal,
        public string $foreignCurrency,
        public int $foreignBalanceMinor,
        public int $recordedBaseMinor,
        public int $revaluedBaseMinor,
        public ?string $subledgerType = null,
        public ?int $subledgerId = null,
    ) {}

    /**
     * The account's own base value change (revalued − recorded), in the
     * account's own signed-balance terms. Positive for a debit-normal
     * account means its base value rose; positive for a credit-normal
     * account means its base value *also* rose in credit-normal terms
     * — which is a loss, not a gain, because owing more is bad. Use
     * `pnlImpactMinor()` for the school's actual gain/loss.
     */
    public function differenceMinor(): int
    {
        return $this->revaluedBaseMinor - $this->recordedBaseMinor;
    }

    /**
     * Positive = gain, negative = loss, from the school's perspective.
     * A debit-normal account (asset) worth more is a gain; a
     * credit-normal account (liability) worth more is a loss.
     */
    public function pnlImpactMinor(): int
    {
        return $this->isDebitNormal ? $this->differenceMinor() : -$this->differenceMinor();
    }
}
