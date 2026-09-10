<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\DataObjects\Notifications\NotificationKeyDefinition;
use Modules\Core\Domain\Exceptions\UnregisteredNotificationKeyException;

/**
 * Book A CORE-09 BR-CORE-09-002. "A module never talks to a gateway.
 * It dispatches a notification intent to this bus" — this is the list
 * of intents the bus knows how to carry. Code-owns-the-list, same
 * split as `TemplateVariableRegistry`/`SettingDefinitionRegistry`.
 */
final class NotificationKeyRegistry
{
    /**
     * @var array<string, NotificationKeyDefinition>
     */
    private static array $keys = [];

    public static function register(NotificationKeyDefinition $definition): void
    {
        self::$keys[$definition->key] = $definition;
    }

    public static function get(string $key): NotificationKeyDefinition
    {
        return self::$keys[$key] ?? throw new UnregisteredNotificationKeyException(
            "Notification key [{$key}] has not been registered by any module.",
            ['key' => $key],
        );
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$keys);
    }

    /**
     * @return array<string, NotificationKeyDefinition>
     */
    public static function all(): array
    {
        return self::$keys;
    }

    public static function clear(): void
    {
        self::$keys = [];
    }
}
