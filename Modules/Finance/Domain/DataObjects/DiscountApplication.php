<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Models\DiscountAward;

/**
 * Book K FIN-07 §3 ⭐ — one award's contribution to a fee line's
 * discount, or a refusal naming why. `gross_minor` is never touched
 * by any of this (BR-FIN-07-001) — this is purely the `discount_minor`
 * side of the computation.
 */
final readonly class DiscountApplication
{
    private function __construct(
        public DiscountAward $award,
        public Money $discountAmount,
        public ?int $contraAccountId,
        public bool $isBlocked,
        public ?string $blockedReason,
    ) {}

    public static function apply(DiscountAward $award, Money $discountAmount, ?int $contraAccountId): self
    {
        return new self($award, $discountAmount, $contraAccountId, false, null);
    }

    public static function blocked(DiscountAward $award, Currency $currency, string $reason): self
    {
        return new self($award, Money::zero($currency), null, true, $reason);
    }
}
