<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Contracts;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\DataObjects\ConvertedAmount;

/**
 * Book B FIN-01 §6 step 5/FIN-06 §4 ("rate resolution"). `PostJournalAction`
 * depends on this to convert every line into the school's base currency
 * before posting — FIN-01 is built before FIN-06, so this contract is
 * where that dependency is deferred. `NoFxCurrencyConverter` (bound by
 * default) only handles same-currency amounts and throws
 * `NoExchangeRateException` for anything else, honestly reflecting that
 * no rate registry exists yet; `FinanceServiceProvider` rebinds this to a
 * real rate-lookup implementation once FIN-06 ships.
 */
interface CurrencyConverter
{
    public function convert(Money $amount, Currency $to, CarbonInterface $at, int $schoolId): ConvertedAmount;
}
