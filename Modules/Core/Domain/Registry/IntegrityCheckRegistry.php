<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;

/**
 * Book A CORE-08 §4. Code owns the list — the same split as
 * `RolloverHandlerRegistry`/`SeedPackRegistry`.
 */
final class IntegrityCheckRegistry
{
    /**
     * @var array<string, IntegrityCheck>
     */
    private static array $checks = [];

    public static function register(IntegrityCheck $check): void
    {
        self::$checks[$check->checkType()] = $check;
    }

    /**
     * @return array<string, IntegrityCheck>
     */
    public static function all(): array
    {
        return self::$checks;
    }

    public static function find(string $checkType): ?IntegrityCheck
    {
        return self::$checks[$checkType] ?? null;
    }

    public static function clear(): void
    {
        self::$checks = [];
    }
}
