<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use InvalidArgumentException;
use JsonSerializable;
use Modules\Core\Domain\Exceptions\CurrencyMismatchException;
use NumberFormatter;

/**
 * All monetary values are integers in minor units, never a float
 * (ADR-006, BR-GLOBAL-020). This is the only sanctioned way to construct,
 * combine, or format money anywhere in the platform.
 */
final readonly class Money implements JsonSerializable
{
    private function __construct(
        public int $minor,
        public Currency $currency,
    ) {}

    public static function of(int $minor, Currency $currency): self
    {
        return new self($minor, $currency);
    }

    /**
     * The only way to construct Money from a human-entered amount.
     * Accepts a string, never a float — see BR-GLOBAL-020.
     */
    public static function fromDecimal(string $decimal, Currency $currency): self
    {
        $decimal = trim($decimal);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $decimal) || ! is_numeric($decimal)) {
            throw new InvalidArgumentException("Invalid decimal amount: [{$decimal}].");
        }

        $scale = $currency->decimals();
        $scaled = bcmul($decimal, bcpow('10', (string) $scale), $scale + 6);

        return new self(self::roundHalfEven($scaled), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self(0, $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function multiplyBy(string $factor, RoundingMode $mode = RoundingMode::Banker): self
    {
        $factor = trim($factor);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $factor) || ! is_numeric($factor)) {
            throw new InvalidArgumentException("Invalid multiplication factor: [{$factor}].");
        }

        $scaled = bcmul((string) $this->minor, $factor, 10);

        $minor = match ($mode) {
            RoundingMode::Banker => self::roundHalfEven($scaled),
            RoundingMode::HalfUp => self::roundHalfUp($scaled),
        };

        return new self($minor, $this->currency);
    }

    /**
     * Distributes this amount across the given ratios, e.g. splitting a
     * receipt across invoice lines. Any rounding remainder goes to the
     * largest share first, deterministically (BR-GLOBAL-022) — the sum of
     * the returned allocations always equals the original to the cent.
     *
     * @param  array<int|string, int>  $ratios
     * @return array<int|string, self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('At least one ratio is required.');
        }

        $total = array_sum($ratios);

        if ($total <= 0) {
            throw new InvalidArgumentException('Ratios must sum to a positive number.');
        }

        $shares = [];
        $allocated = 0;

        foreach ($ratios as $key => $ratio) {
            $share = (int) bcdiv(bcmul((string) $this->minor, (string) $ratio), (string) $total, 0);
            $shares[$key] = $share;
            $allocated += $share;
        }

        $remainder = $this->minor - $allocated;

        if ($remainder !== 0) {
            $order = $ratios;
            arsort($order);
            $step = $remainder > 0 ? 1 : -1;

            foreach (array_keys($order) as $key) {
                if ($remainder === 0) {
                    break;
                }

                $shares[$key] += $step;
                $remainder -= $step;
            }
        }

        return array_map(fn (int $minor): self => new self($minor, $this->currency), $shares);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function compareTo(self $other): int
    {
        $this->assertSameCurrency($other);

        return $this->minor <=> $other->minor;
    }

    public function toDecimal(): string
    {
        $scale = $this->currency->decimals();

        return bcdiv((string) $this->minor, bcpow('10', (string) $scale), $scale);
    }

    public function format(?string $locale = null): string
    {
        if (! class_exists(NumberFormatter::class)) {
            return sprintf('%s %s', $this->currency->value, $this->toDecimal());
        }

        $formatter = new NumberFormatter($locale ?? 'en_ZW', NumberFormatter::CURRENCY);
        $formatted = $formatter->formatCurrency((float) $this->toDecimal(), $this->currency->value);

        return $formatted !== false ? $formatted : sprintf('%s %s', $this->currency->value, $this->toDecimal());
    }

    /**
     * @return array{amount_minor: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount_minor' => $this->minor,
            'currency' => $this->currency->value,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException(
                sprintf('Cannot combine %s with %s directly — convert through the FX service first.', $this->currency->value, $other->currency->value),
                ['left_currency' => $this->currency->value, 'right_currency' => $other->currency->value],
            );
        }
    }

    private static function roundHalfEven(string $scaledDecimal): int
    {
        $negative = str_starts_with($scaledDecimal, '-');
        $value = ltrim($scaledDecimal, '-');

        [$intPart, $fracPart] = array_pad(explode('.', $value, 2), 2, '');
        $intPart = $intPart === '' ? '0' : $intPart;
        $whole = (int) $intPart;

        if ($fracPart === '') {
            $result = $whole;
        } else {
            $firstDecimal = (int) $fracPart[0];
            $remainder = ltrim(substr($fracPart, 1), '0');

            $result = match (true) {
                $firstDecimal < 5 => $whole,
                $firstDecimal > 5, $remainder !== '' => $whole + 1,
                default => $whole % 2 === 0 ? $whole : $whole + 1,
            };
        }

        return $negative ? -$result : $result;
    }

    private static function roundHalfUp(string $scaledDecimal): int
    {
        $negative = str_starts_with($scaledDecimal, '-');
        $value = ltrim($scaledDecimal, '-');

        [$intPart, $fracPart] = array_pad(explode('.', $value, 2), 2, '');
        $whole = (int) ($intPart === '' ? '0' : $intPart);

        if ($fracPart !== '' && (int) $fracPart[0] >= 5) {
            $whole++;
        }

        return $negative ? -$whole : $whole;
    }
}
