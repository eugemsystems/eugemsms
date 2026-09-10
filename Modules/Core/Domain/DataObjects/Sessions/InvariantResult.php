<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

/**
 * BR-CORE-03-019: Σ(closing balances of term N) ≡ Σ(opening balances of
 * term N+1), per currency, with FX movement accounted separately.
 */
final readonly class InvariantResult
{
    /**
     * @param  array<string, array{closing: string, opening: string, difference: string}>  $perCurrency
     *                                                                                                   keyed by ISO currency code, values as Money-precision decimal strings
     */
    public function __construct(
        public bool $holds,
        public array $perCurrency,
    ) {}
}
