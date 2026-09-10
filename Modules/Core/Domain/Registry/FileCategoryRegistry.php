<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\DataObjects\Files\FileCategoryDefinition;
use Modules\Core\Models\FileCategory;

/**
 * Book A CORE-10 §2/BR-CORE-10-001. Every upload declares a registered
 * category; an unregistered one is rejected outright. Code owns the
 * list, `syncToDatabase()` mirrors it into `file_categories` — the
 * same split as `SettingDefinitionRegistry`.
 */
final class FileCategoryRegistry
{
    /**
     * @var array<string, FileCategoryDefinition>
     */
    private static array $categories = [];

    public static function register(FileCategoryDefinition $definition): void
    {
        self::$categories[$definition->key] = $definition;
    }

    public static function get(string $key): ?FileCategoryDefinition
    {
        return self::$categories[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$categories);
    }

    /**
     * @return array<string, FileCategoryDefinition>
     */
    public static function all(): array
    {
        return self::$categories;
    }

    public static function clear(): void
    {
        self::$categories = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$categories as $key => $definition) {
            $keys[] = $key;

            FileCategory::updateOrCreate(['key' => $key], [
                'label' => $definition->label,
                'module_code' => $definition->moduleCode,
                'allowed_mimes' => $definition->allowedMimes,
                'max_size_bytes' => $definition->maxSizeBytes,
                'is_sensitive' => $definition->isSensitive,
                'generates_variants' => $definition->generatesVariants,
                'requires_expiry' => $definition->requiresExpiry,
                'retention_years' => $definition->retentionYears,
            ]);
        }

        FileCategory::query()->whereNotIn('key', $keys)->delete();
    }
}
