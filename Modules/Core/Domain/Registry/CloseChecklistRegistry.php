<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Sessions\CloseChecklistItem;
use Modules\Core\Domain\Support\PeriodType;

/**
 * Book A CORE-03 §6 — mirrors `RolloverHandlerRegistry`'s split: code
 * owns the list, modules register their own items from their service
 * provider's `boot()`.
 */
final class CloseChecklistRegistry
{
    /**
     * @var array<int, CloseChecklistItem>
     */
    private static array $items = [];

    public static function register(CloseChecklistItem $item): void
    {
        self::$items[] = $item;
    }

    /**
     * @return array<int, CloseChecklistItem>
     */
    public static function all(): array
    {
        return self::$items;
    }

    /**
     * @return array<int, CloseChecklistItem>
     */
    public static function for(PeriodType $type): array
    {
        return array_values(array_filter(
            self::$items,
            fn (CloseChecklistItem $item): bool => $item->appliesTo() === $type,
        ));
    }

    public static function clear(): void
    {
        self::$items = [];
    }
}
