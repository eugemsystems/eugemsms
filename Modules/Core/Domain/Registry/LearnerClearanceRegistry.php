<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Closure;

/**
 * Where each module says what must be settled before a learner leaves (Book C
 * PPL-01 BR-PPL-01-014: library items back, boarding property back, fees
 * settled or an agreed arrangement). A module registers one check; People's
 * `CheckLearnerClearanceAction` runs them all, so People never imports the
 * modules that own the property.
 */
final class LearnerClearanceRegistry
{
    /**
     * @var array<string, Closure(int, int): array<int, string>>
     */
    private static array $checks = [];

    /**
     * @param  Closure(int, int): array<int, string>  $check  (schoolId, studentId) => list of reasons the learner is NOT clear
     */
    public static function register(string $key, Closure $check): void
    {
        self::$checks[$key] = $check;
    }

    /**
     * @return array<string, Closure(int, int): array<int, string>>
     */
    public static function all(): array
    {
        return self::$checks;
    }

    public static function forget(string $key): void
    {
        unset(self::$checks[$key]);
    }

    public static function clear(): void
    {
        self::$checks = [];
    }
}
