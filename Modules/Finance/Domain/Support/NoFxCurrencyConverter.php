<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\DataObjects\ConvertedAmount;
use Modules\Finance\Domain\Exceptions\NoExchangeRateException;

/**
 * The default `CurrencyConverter` until FIN-06 ships a real rate
 * registry. Same-currency amounts convert trivially at rate 1; anything
 * else throws — there is no fallback rate anywhere in this system
 * (BR-FIN-06-007), and that rule holds even before FIN-06 exists to
 * enforce it structurally.
 */
final class NoFxCurrencyConverter implements CurrencyConverter
{
    public function convert(Money $amount, Currency $to, CarbonInterface $at, int $schoolId): ConvertedAmount
    {
        if ($amount->currency === $to) {
            return new ConvertedAmount($amount, '1.0000000000');
        }

        throw NoExchangeRateException::forPair($amount->currency->value, $to->value, $at->toDateString());
    }
}
