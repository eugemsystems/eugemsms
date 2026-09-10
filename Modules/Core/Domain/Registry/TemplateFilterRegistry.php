<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Closure;
use Modules\Core\Domain\Exceptions\TemplateSandboxViolationException;

/**
 * Book A CORE-06 §4. The complete set of filters a template may call —
 * `{{ value | upper }}`. Nothing outside this registry is callable from
 * template content (BR-CORE-06-009); `CoreServiceProvider` registers
 * the built-in catalogue, and a module registering its own template
 * type may add more.
 */
final class TemplateFilterRegistry
{
    /**
     * @var array<string, Closure(mixed, array<int, string>): mixed>
     */
    private static array $filters = [];

    /**
     * @param  Closure(mixed, array<int, string>): mixed  $filter
     */
    public static function register(string $name, Closure $filter): void
    {
        self::$filters[$name] = $filter;
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$filters);
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::$filters);
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function apply(string $name, mixed $value, array $args = []): mixed
    {
        if (! self::has($name)) {
            throw new TemplateSandboxViolationException("Filter [{$name}] is not registered.", ['filter' => $name]);
        }

        return (self::$filters[$name])($value, $args);
    }

    public static function clear(): void
    {
        self::$filters = [];
    }
}
