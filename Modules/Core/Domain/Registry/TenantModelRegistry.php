<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\School;

/**
 * The tenancy isolation test generator's source of truth (Book A Part
 * 1.11 / CORE testing standards): "generated from the model registry —
 * adding a model without a passing isolation test fails CI."
 *
 * Every module that owns a `BelongsToSchool` model registers it here, in
 * its service provider's boot(), with a closure that creates one valid
 * row for a given school. `TenancyIsolationTest` iterates this registry
 * and proves cross-school isolation for every registered model.
 */
final class TenantModelRegistry
{
    /**
     * @var array<class-string<Model>, Closure(School): Model>
     */
    private static array $models = [];

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(School): Model  $createForSchool
     */
    public static function register(string $modelClass, Closure $createForSchool): void
    {
        self::$models[$modelClass] = $createForSchool;
    }

    /**
     * @return array<class-string<Model>, Closure(School): Model>
     */
    public static function all(): array
    {
        return self::$models;
    }

    public static function clear(): void
    {
        self::$models = [];
    }
}
