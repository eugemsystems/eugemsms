<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

/**
 * Book A CORE-06 §4/BR-CORE-06-010. Each template type registers its
 * own available variable set (dotted paths, e.g. `learner.full_name`);
 * a template referencing anything outside that set fails validation at
 * save time — the same code-owns-the-list split as
 * `TemplateFilterRegistry` and `SettingDefinitionRegistry`. A trailing
 * `.*` entry allows any child path under that prefix (e.g.
 * `results.*` for a `@foreach(results as result)` loop body referring
 * to `result.mark`, `result.grade`, ...).
 */
final class TemplateVariableRegistry
{
    /**
     * @var array<string, array<int, string>>
     */
    private static array $variables = [];

    /**
     * @param  array<int, string>  $paths
     */
    public static function register(string $templateType, array $paths): void
    {
        self::$variables[$templateType] = array_unique([...(self::$variables[$templateType] ?? []), ...$paths]);
    }

    /**
     * @return array<int, string>
     */
    public static function for(string $templateType): array
    {
        return self::$variables[$templateType] ?? [];
    }

    public static function isRegistered(string $templateType, string $path): bool
    {
        foreach (self::for($templateType) as $registered) {
            if ($registered === $path) {
                return true;
            }

            if (str_ends_with($registered, '.*') && str_starts_with($path, substr($registered, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    public static function clear(): void
    {
        self::$variables = [];
    }
}
