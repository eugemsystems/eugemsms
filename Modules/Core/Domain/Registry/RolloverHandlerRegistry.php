<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\Support\Sessions\PendingRolloverHandler;
use Modules\Core\Models\RolloverHandlerRegistration;

/**
 * Book A CORE-03 §5 — the in-code source of truth for the roll-over
 * pipeline. Every module registers its handler(s) from its own service
 * provider's `boot()`, in whatever order — this registry sorts by
 * `sortOrder()` on read, matching `SeedPackRegistry`'s "code owns the
 * list, the `rollover_handlers` table is a queryable mirror" split.
 */
final class RolloverHandlerRegistry
{
    /**
     * @var array<int, RolloverHandler>
     */
    private static array $handlers = [];

    public static function register(RolloverHandler $handler): void
    {
        self::$handlers[] = $handler;
    }

    /**
     * @return array<int, RolloverHandler> sorted by sortOrder(), ascending
     */
    public static function all(): array
    {
        $handlers = self::$handlers;

        usort($handlers, fn (RolloverHandler $a, RolloverHandler $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $handlers;
    }

    /**
     * @return array<int, RolloverHandler> only handlers whose module is
     *                                     actually installed — every
     *                                     `PendingRolloverHandler` excluded
     */
    public static function available(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (RolloverHandler $handler): bool => ! $handler instanceof PendingRolloverHandler,
        ));
    }

    public static function clear(): void
    {
        self::$handlers = [];
    }

    /**
     * Mirrors the in-code registry into `rollover_handlers` so it's
     * queryable/reportable without booting the whole application —
     * `SeedPackRegistry` has no equivalent because seed packs are never
     * queried outside a booted app; the roll-over wizard's history and
     * audit screens need to be.
     */
    public static function syncToDatabase(): void
    {
        $classes = [];

        foreach (self::all() as $handler) {
            $classes[] = $handler::class;

            RolloverHandlerRegistration::updateOrCreate(
                ['handler_class' => $handler::class],
                [
                    'module_code' => $handler->moduleCode(),
                    'sort_order' => $handler->sortOrder(),
                    'is_blocking' => $handler->isBlocking(),
                ],
            );
        }

        RolloverHandlerRegistration::query()->whereNotIn('handler_class', $classes)->delete();
    }
}
