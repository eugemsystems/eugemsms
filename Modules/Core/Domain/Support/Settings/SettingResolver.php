<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Domain\Exceptions\UnregisteredSettingException;
use Modules\Core\Models\SettingDefinition;
use Modules\Core\Models\SettingValue;

/**
 * Book A CORE-04 §3 ⭐ — the resolution algorithm. BR-CORE-04-002:
 * resolution walks from most specific to least; the first defined value
 * wins, else the definition default applies.
 */
final class SettingResolver
{
    private const int TTL = 3600;

    /**
     * A cached-but-absent value is a legitimate result (the scope was
     * checked and has none) — this sentinel distinguishes "not in cache
     * yet" from "cached as having no value", so a cache hit for "no
     * value here" doesn't fall through to a DB query on every call.
     */
    private const string MISS = "\0__miss__\0";

    public function get(string $key, ?ScopeChain $chain = null): mixed
    {
        $definition = SettingDefinition::where('key', $key)->first();

        if ($definition === null) {
            throw new UnregisteredSettingException(
                "Setting [{$key}] has not been registered by any module.",
                ['key' => $key],
            );
        }

        $chain ??= ScopeChain::fromCurrentContext();

        foreach ($chain->descendingSpecificity() as ['scope' => $scope, 'id' => $id]) {
            $cacheKey = "setting:{$key}:{$scope->value}:{$id}";
            $cached = Cache::get($cacheKey, self::MISS);

            if ($cached !== self::MISS) {
                return $cached === null
                    ? SettingCaster::cast($definition, $definition->default_value)
                    : SettingCaster::cast($definition, $this->decrypt($definition, $cached));
            }

            $row = SettingValue::where('setting_key', $key)
                ->where('scope_type', $scope)
                ->where('scope_id', $id)
                ->first();

            Cache::put($cacheKey, $row?->value, self::TTL);

            if ($row !== null) {
                return SettingCaster::cast($definition, $this->decrypt($definition, $row->value));
            }
        }

        return SettingCaster::cast($definition, $definition->default_value);
    }

    /**
     * BR-CORE-04-005/the resolution algorithm's cache invalidation note:
     * writing a value at scope S flushes that key at S and every scope
     * below it — more specific scopes can't be affected by a less
     * specific write, but the flat per-scope-id cache key here can't be
     * targeted by a single tag operation, so every level is flushed
     * explicitly by whoever calls this after a write (see
     * `SetSettingValueAction`).
     */
    public static function forget(string $key, SettingScope $scope, int $id): void
    {
        Cache::forget("setting:{$key}:{$scope->value}:{$id}");
    }

    private function decrypt(SettingDefinition $definition, ?string $value): ?string
    {
        if ($value === null || ! $definition->is_encrypted) {
            return $value;
        }

        return Crypt::decryptString($value);
    }
}
