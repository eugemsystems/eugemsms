<?php

use Modules\Core\Domain\Exceptions\CurrencyMismatchException;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\RoundingMode;

it('constructs from minor units', function () {
    $money = Money::of(125050, Currency::USD);

    expect($money->minor)->toBe(125050)
        ->and($money->currency)->toBe(Currency::USD)
        ->and($money->toDecimal())->toBe('1250.50');
});

it('constructs from a decimal string', function () {
    $money = Money::fromDecimal('1250.50', Currency::USD);

    expect($money->minor)->toBe(125050);
});

it('never accepts a float for fromDecimal', function () {
    // fromDecimal's signature only accepts string — this documents the
    // BR-GLOBAL-020 intent by asserting the string path round-trips exactly
    // what a float would have silently corrupted.
    $money = Money::fromDecimal('0.1', Currency::USD);

    expect($money->minor)->toBe(10);
});

it('rejects a malformed decimal string', function () {
    Money::fromDecimal('not-a-number', Currency::USD);
})->throws(InvalidArgumentException::class);

it('adds and subtracts money of the same currency', function () {
    $a = Money::fromDecimal('10.00', Currency::USD);
    $b = Money::fromDecimal('2.50', Currency::USD);

    expect($a->plus($b)->toDecimal())->toBe('12.50')
        ->and($a->minus($b)->toDecimal())->toBe('7.50');
});

it('throws combining different currencies', function () {
    $usd = Money::fromDecimal('10.00', Currency::USD);
    $zwg = Money::fromDecimal('10.00', Currency::ZWG);

    $usd->plus($zwg);
})->throws(CurrencyMismatchException::class);

it('exposes a stable machine error code on currency mismatch', function () {
    $usd = Money::fromDecimal('10.00', Currency::USD);
    $zwg = Money::fromDecimal('10.00', Currency::ZWG);

    try {
        $usd->plus($zwg);
    } catch (CurrencyMismatchException $exception) {
        expect($exception->errorCode())->toBe('CURRENCY_MISMATCH')
            ->and($exception->httpStatus())->toBe(422);

        return;
    }

    expect(false)->toBeTrue('Expected CurrencyMismatchException to be thrown.');
});

it('applies banker\'s rounding by default', function () {
    $money = Money::of(100, Currency::USD); // $1.00

    // 100 * 0.125 = 12.5 -> rounds to even (12)
    expect($money->multiplyBy('0.125')->minor)->toBe(12);

    // 100 * 0.135 -> intermediate 13.5 -> rounds to even (14)
    $other = Money::of(1000, Currency::USD);
    expect($other->multiplyBy('0.0135')->minor)->toBe(14);
});

it('supports half-up rounding explicitly', function () {
    $money = Money::of(100, Currency::USD);

    expect($money->multiplyBy('0.125', RoundingMode::HalfUp)->minor)->toBe(13);
});

it('allocates without losing or gaining a cent', function () {
    $money = Money::of(100, Currency::USD); // $1.00 split 3 ways

    $shares = $money->allocate([1, 1, 1]);

    expect(array_sum(array_map(fn (Money $m) => $m->minor, $shares)))->toBe(100)
        ->and($shares[0]->minor)->toBe(34)
        ->and($shares[1]->minor)->toBe(33)
        ->and($shares[2]->minor)->toBe(33);
});

it('allocates the remainder to the largest share first, deterministically', function () {
    $money = Money::of(100, Currency::USD);

    // 200/3 = 66.67 -> floor 66, 100/3 = 33.33 -> floor 33; remainder 1 cent
    // must land on the larger ('a') share, every time.
    $first = $money->allocate(['a' => 2, 'b' => 1]);
    $second = $money->allocate(['a' => 2, 'b' => 1]);

    expect($first['a']->minor)->toBe(67)
        ->and($first['b']->minor)->toBe(33)
        ->and($first['a']->minor)->toBe($second['a']->minor)
        ->and($first['b']->minor)->toBe($second['b']->minor)
        ->and($first['a']->minor + $first['b']->minor)->toBe(100);
});

it('reports zero and negative amounts', function () {
    expect(Money::zero(Currency::USD)->isZero())->toBeTrue()
        ->and(Money::of(-1, Currency::USD)->isNegative())->toBeTrue()
        ->and(Money::of(1, Currency::USD)->isNegative())->toBeFalse();
});

it('compares amounts of the same currency', function () {
    $a = Money::of(100, Currency::USD);
    $b = Money::of(200, Currency::USD);

    expect($a->compareTo($b))->toBeLessThan(0)
        ->and($b->compareTo($a))->toBeGreaterThan(0)
        ->and($a->compareTo($a))->toBe(0);
});

it('serialises to the API money envelope, never a bare number', function () {
    $money = Money::fromDecimal('1250.50', Currency::USD);

    $json = $money->jsonSerialize();

    expect($json)->toHaveKeys(['amount_minor', 'currency', 'formatted'])
        ->and($json['amount_minor'])->toBe(125050)
        ->and($json['currency'])->toBe('USD');
});
