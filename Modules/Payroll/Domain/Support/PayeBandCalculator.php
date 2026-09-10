<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use UnexpectedValueException;

/**
 * Book H3 PPL-05 §3 ⭐/BR-PPL-05-005. Applies a progressive band table
 * to a taxable base — each band's rate applies only to the slice of
 * income that falls within it, never to the whole base at the top
 * marginal rate.
 *
 * Expected `statutory_configurations.configuration` shape for
 * `paye_bands`:
 * `{"bands": [{"from_minor": 0, "to_minor": 10000, "rate": "0.00"}, {"from_minor": 10000, "to_minor": null, "rate": "0.20"}]}`
 * — `to_minor: null` means the band is open-ended (the top bracket).
 *
 * `$configuration` is typed `array<string, mixed>` because it comes
 * straight off an admin-editable JSON column — the shape above is
 * what's expected, not statically guaranteed, so each band is
 * validated on the way in rather than assumed.
 */
final class PayeBandCalculator
{
    /**
     * @param  array<string, mixed>  $configuration
     * @return array{total: Money, bandsApplied: array<int, array<string, mixed>>}
     */
    public function apply(array $configuration, Money $base, Currency $currency): array
    {
        $total = Money::zero($currency);
        $bandsApplied = [];

        if ($base->isNegative() || $base->isZero()) {
            return ['total' => $total, 'bandsApplied' => $bandsApplied];
        }

        if (! is_array($configuration['bands'] ?? null)) {
            throw new UnexpectedValueException('A paye_bands configuration must have a "bands" array.');
        }

        foreach ($configuration['bands'] as $band) {
            if (! is_array($band) || ! is_int($band['from_minor'] ?? null) || ! is_string($band['rate'] ?? null)) {
                throw new UnexpectedValueException('Each PAYE band requires an integer from_minor and a string rate.');
            }

            $toMinor = $band['to_minor'] ?? null;

            if ($toMinor !== null && ! is_int($toMinor)) {
                throw new UnexpectedValueException('A PAYE band\'s to_minor must be an integer or null.');
            }

            $from = Money::of($band['from_minor'], $currency);
            $to = $toMinor !== null ? Money::of($toMinor, $currency) : null;

            if ($base->compareTo($from) <= 0) {
                continue;
            }

            $sliceTop = $to !== null && $to->compareTo($base) < 0 ? $to : $base;
            $sliceWidth = $sliceTop->minus($from);

            if ($sliceWidth->isNegative() || $sliceWidth->isZero()) {
                continue;
            }

            $amount = $sliceWidth->multiplyBy($band['rate']);
            $total = $total->plus($amount);

            $bandsApplied[] = [
                'from_minor' => $from->minor,
                'to_minor' => $to?->minor,
                'rate' => $band['rate'],
                'slice_minor' => $sliceWidth->minor,
                'amount_minor' => $amount->minor,
            ];
        }

        return ['total' => $total, 'bandsApplied' => $bandsApplied];
    }
}
