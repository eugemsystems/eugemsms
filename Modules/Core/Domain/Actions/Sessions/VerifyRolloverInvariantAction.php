<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Sessions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Contracts\Sessions\TermBalanceProvider;
use Modules\Core\Domain\DataObjects\Sessions\InvariantResult;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;

/**
 * ACT-VerifyRolloverInvariant (Book A CORE-03 §4). BR-CORE-03-019: for
 * every currency, Σ(closing balances of term N) ≡ Σ(opening balances of
 * term N+1). Compares via `Money`, the same integer-minor-units value
 * object every monetary comparison in the platform uses (ADR-006,
 * BR-GLOBAL-020) — never float arithmetic, which cannot represent every
 * decimal money value exactly and would risk the check itself
 * introducing the drift it exists to catch.
 */
final class VerifyRolloverInvariantAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly TermBalanceProvider $balanceProvider,
    ) {}

    public function execute(Term $closingTerm, Term $openingTerm): InvariantResult
    {
        $closing = $this->balanceProvider->closingBalances($closingTerm);
        $opening = $this->balanceProvider->openingBalances($openingTerm);

        $currencies = array_unique([...array_keys($closing), ...array_keys($opening)]);

        $perCurrency = [];
        $holds = true;

        foreach ($currencies as $code) {
            $currency = Currency::from($code);
            $closingAmount = $closing[$code] ?? Money::zero($currency);
            $openingAmount = $opening[$code] ?? Money::zero($currency);
            $difference = $openingAmount->minus($closingAmount);

            if (! $difference->isZero()) {
                $holds = false;
            }

            $perCurrency[$code] = [
                'closing' => $closingAmount->toDecimal(),
                'opening' => $openingAmount->toDecimal(),
                'difference' => $difference->toDecimal(),
            ];
        }

        return new InvariantResult($holds, $perCurrency);
    }
}
