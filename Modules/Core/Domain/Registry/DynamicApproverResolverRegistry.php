<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Closure;
use Modules\Core\Domain\Contracts\Approvals\Approvable;

/**
 * Book A CORE-07 §2/BR-CORE-07-002. A step's `dynamic_resolver` string
 * (`'housemaster_of_learner'`, `'hod_of_subject'`, ...) names a
 * resolver registered here by the module that owns the relationship —
 * CORE-07 has no idea what a housemaster is. No entries exist until a
 * module registers one; a step referencing an unregistered resolver
 * has no resolvable approver (BR-CORE-07-003).
 */
final class DynamicApproverResolverRegistry
{
    /**
     * @var array<string, Closure(Approvable): array<int, int>>
     */
    private static array $resolvers = [];

    /**
     * @param  Closure(Approvable): array<int, int>  $resolver  returns user ids
     */
    public static function register(string $name, Closure $resolver): void
    {
        self::$resolvers[$name] = $resolver;
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$resolvers);
    }

    /**
     * @return array<int, int>
     */
    public static function resolve(string $name, Approvable $approvable): array
    {
        return self::has($name) ? (self::$resolvers[$name])($approvable) : [];
    }

    public static function clear(): void
    {
        self::$resolvers = [];
    }
}
