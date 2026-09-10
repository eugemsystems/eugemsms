<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Core\Domain\Support\Money;

final readonly class RealisedFxResult
{
    public function __construct(
        public Money $bankBaseAmount,
        public Money $obligationReliefAmount,
        public ?int $exchangeRateId,
    ) {}

    /**
     * Positive minor = gain, negative = loss.
     */
    public function differenceMinor(): int
    {
        return $this->bankBaseAmount->minor - $this->obligationReliefAmount->minor;
    }

    public function isGain(): bool
    {
        return $this->differenceMinor() > 0;
    }

    public function isLoss(): bool
    {
        return $this->differenceMinor() < 0;
    }
}
