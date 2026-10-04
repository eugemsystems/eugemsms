<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class BillingAttributeChangePreview
{
    public function __construct(
        public ?int $currentAmountMinor,
        public ?string $currentCurrency,
        public ?int $proposedAmountMinor,
        public ?string $proposedCurrency,
    ) {}

    public function hasComparableFigures(): bool
    {
        return $this->currentAmountMinor !== null
            && $this->proposedAmountMinor !== null
            && $this->currentCurrency === $this->proposedCurrency;
    }

    public function deltaMinor(): ?int
    {
        return $this->hasComparableFigures() ? $this->proposedAmountMinor - $this->currentAmountMinor : null;
    }
}
