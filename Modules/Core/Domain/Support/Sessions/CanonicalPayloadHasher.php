<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

/**
 * BR-CORE-03-015: "SHA-256 over a canonically serialised payload (sorted
 * keys, fixed number formatting)" — two calls with the same logical data
 * must produce byte-identical JSON regardless of insertion order, so the
 * hash is reproducible for chain verification years later.
 */
final class CanonicalPayloadHasher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function hash(array $payload): string
    {
        return hash('sha256', self::canonicalize($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function canonicalize(array $payload): string
    {
        $sorted = self::sortRecursively($payload);

        return json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);

        $sorted = array_map(self::sortRecursively(...), $value);

        if (! $isList) {
            ksort($sorted);
        }

        return $sorted;
    }
}
