<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * The currencies sERP knows how to handle natively (Volume 1 §1.3, §2.6).
 * FIN-06 owns the full currency *registry* (rates, sources, display
 * formats); this enum is the closed set of ISO-4217-style codes that the
 * Money value object is allowed to be constructed with.
 */
enum Currency: string
{
    case USD = 'USD';
    case ZWG = 'ZWG';

    public function decimals(): int
    {
        return match ($this) {
            self::USD, self::ZWG => 2,
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::ZWG => 'ZiG',
        };
    }
}
