<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Finance\Domain\Contracts\CurrencyConverter;
use Modules\Finance\Domain\DataObjects\ConvertedAmount;
use Modules\Finance\Domain\Exceptions\NoExchangeRateException;
use Modules\Finance\Models\ExchangeRate;

/**
 * Book B FIN-06 §4 ⭐ "rate resolution" — the real `CurrencyConverter`,
 * replacing FIN-01's `NoFxCurrencyConverter` now that a rate registry
 * exists. Tries the direct pair first, then the inverse pair (a school
 * that captures ZWG→USD doesn't need a second row for USD→ZWG). There
 * is no fallback rate: a missing rate throws, never assumes 1:1
 * (BR-FIN-06-007).
 */
final class RateResolvingCurrencyConverter implements CurrencyConverter
{
    public function convert(Money $amount, Currency $to, CarbonInterface $at, int $schoolId): ConvertedAmount
    {
        if ($amount->currency === $to) {
            return new ConvertedAmount($amount, '1.0000000000');
        }

        $direct = $this->rateInEffectAt($schoolId, $amount->currency, $to, $at);

        if ($direct !== null) {
            $converted = Money::of($amount->minor, $to)->multiplyBy($direct->rate);

            return new ConvertedAmount($converted, $direct->rate, $direct->id);
        }

        $inverse = $this->rateInEffectAt($schoolId, $to, $amount->currency, $at);

        if ($inverse !== null) {
            $converted = Money::of($amount->minor, $to)->multiplyBy($inverse->inverse_rate);

            return new ConvertedAmount($converted, $inverse->inverse_rate, $inverse->id);
        }

        throw NoExchangeRateException::forPair($amount->currency->value, $to->value, $at->toDateString());
    }

    /**
     * "In effect at $at" is not the same as "currently active":
     * BR-FIN-06-008 requires a backdated transaction to use the rate
     * that was genuinely in force on its own date, and a rate that has
     * since been superseded is still that historically-correct rate for
     * the window it originally covered. `pending` (never took effect)
     * and `rejected` are the only statuses excluded.
     */
    private function rateInEffectAt(int $schoolId, Currency $from, Currency $to, CarbonInterface $at): ?ExchangeRate
    {
        return ExchangeRate::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('from_currency', $from->value)
            ->where('to_currency', $to->value)
            ->whereIn('status', ['active', 'superseded'])
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>', $at))
            ->orderByDesc('effective_from')
            ->first();
    }
}
