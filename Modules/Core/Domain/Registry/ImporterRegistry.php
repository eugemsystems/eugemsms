<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Imports\Importer;
use Modules\Core\Domain\DataObjects\Imports\ImportDefinitionData;
use Modules\Core\Models\ImportDefinition;

/**
 * Book A CORE-11 §2/§5. Code owns the list; `syncToDatabase()` mirrors
 * it into `import_definitions` — same split as `FileCategoryRegistry`.
 */
final class ImporterRegistry
{
    /**
     * @var array<string, ImportDefinitionData>
     */
    private static array $definitions = [];

    public static function register(ImportDefinitionData $definition): void
    {
        self::$definitions[$definition->key] = $definition;
    }

    public static function get(string $key): ?ImportDefinitionData
    {
        return self::$definitions[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$definitions);
    }

    /**
     * @return array<string, ImportDefinitionData>
     */
    public static function all(): array
    {
        return self::$definitions;
    }

    public static function resolve(string $key): Importer
    {
        $definition = self::get($key);

        return app($definition->importerClass);
    }

    public static function clear(): void
    {
        self::$definitions = [];
    }

    public static function syncToDatabase(): void
    {
        $keys = [];

        foreach (self::$definitions as $key => $definition) {
            $keys[] = $key;

            ImportDefinition::updateOrCreate(['key' => $key], [
                'label' => $definition->label,
                'module_code' => $definition->moduleCode,
                'importer_class' => $definition->importerClass,
                'description' => $definition->description,
                'required_permission' => $definition->requiredPermission,
                'depends_on' => $definition->dependsOn,
                'is_rollbackable' => $definition->isRollbackable,
                'sort_order' => $definition->sortOrder,
            ]);
        }

        ImportDefinition::query()->whereNotIn('key', $keys)->delete();
    }
}
