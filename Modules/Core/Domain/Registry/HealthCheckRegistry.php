<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Scheduling\HealthCheck;

/**
 * Book A CORE-12 §3. Code owns the list — the same split as
 * `IntegrityCheckRegistry`.
 */
final class HealthCheckRegistry
{
    /**
     * @var array<string, HealthCheck>
     */
    private static array $checks = [];

    public static function register(HealthCheck $check): void
    {
        self::$checks[$check->checkKey()] = $check;
    }

    /**
     * @return array<string, HealthCheck>
     */
    public static function all(): array
    {
        return self::$checks;
    }

    public static function find(string $checkKey): ?HealthCheck
    {
        return self::$checks[$checkKey] ?? null;
    }

    public static function clear(): void
    {
        self::$checks = [];
    }
}
