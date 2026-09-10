<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Models\SettingDefinition;

/**
 * Book A CORE-04 §2 — "a setting must be registered by a module service
 * provider before a value can be stored for it" (BR-CORE-04-001). Every
 * module registers its own settings from its service provider's
 * `boot()`; `syncToDatabase()` mirrors the in-code list into
 * `setting_definitions`, the same code-owns-the-list split as
 * `RolloverHandlerRegistry`/`SeedPackRegistry`.
 */
final class SettingDefinitionRegistry
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private static array $definitions = [];

    /**
     * @param  array<string, mixed>  $attributes  every SettingDefinition column except `key` itself
     */
    public static function register(string $key, array $attributes): void
    {
        self::$definitions[$key] = ['key' => $key, ...$attributes];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::$definitions;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$definitions);
    }

    public static function clear(): void
    {
        self::$definitions = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$definitions as $key => $attributes) {
            $keys[] = $key;

            SettingDefinition::updateOrCreate(['key' => $key], $attributes);
        }

        SettingDefinition::query()->whereNotIn('key', $keys)->delete();
    }
}
