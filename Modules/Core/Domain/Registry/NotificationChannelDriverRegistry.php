<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Registry;

use Modules\Core\Domain\Contracts\Notifications\NotificationChannelDriver;
use Modules\Core\Domain\Support\Notifications\NullNotificationChannelDriver;

/**
 * Book A CORE-09 §1. One driver per channel; `NullNotificationChannelDriver`
 * is the default for any channel nothing more specific has registered.
 */
final class NotificationChannelDriverRegistry
{
    /**
     * @var array<string, NotificationChannelDriver>
     */
    private static array $drivers = [];

    public static function register(NotificationChannelDriver $driver): void
    {
        self::$drivers[$driver->channel()] = $driver;
    }

    public static function forChannel(string $channel): NotificationChannelDriver
    {
        return self::$drivers[$channel] ?? new NullNotificationChannelDriver($channel);
    }

    public static function clear(): void
    {
        self::$drivers = [];
    }
}
