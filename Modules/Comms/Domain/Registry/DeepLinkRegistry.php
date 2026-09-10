<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

/**
 * Book I COM-03 §6/BR-COM-03-011. `linkType => [path template, parent
 * fallback screen]`. **Scope boundary**: a small real starting set,
 * same documented pattern as this book's other registries.
 */
final class DeepLinkRegistry
{
    /**
     * @var array<string, array{path: string, fallback: string}>
     */
    private static array $links = [];

    public static function register(string $linkType, string $path, string $fallback): void
    {
        self::$links[$linkType] = ['path' => $path, 'fallback' => $fallback];
    }

    /**
     * @return array{path: string, fallback: string}|null
     */
    public static function get(string $linkType): ?array
    {
        return self::$links[$linkType] ?? null;
    }

    public static function clear(): void
    {
        self::$links = [];
    }
}
