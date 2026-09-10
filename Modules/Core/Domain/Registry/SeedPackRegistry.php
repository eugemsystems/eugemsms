<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Install\SeedPack;

/**
 * Book A CORE-01 §7 — the Zimbabwe baseline seed packs, registered by
 * whichever module owns each one.
 */
final class SeedPackRegistry
{
    /**
     * @var array<string, SeedPack>
     */
    private static array $packs = [];

    public static function register(SeedPack $pack): void
    {
        self::$packs[$pack->code()] = $pack;
    }

    /**
     * @return array<string, SeedPack>
     */
    public static function all(): array
    {
        return self::$packs;
    }

    public static function find(string $code): ?SeedPack
    {
        return self::$packs[$code] ?? null;
    }

    public static function clear(): void
    {
        self::$packs = [];
    }
}
