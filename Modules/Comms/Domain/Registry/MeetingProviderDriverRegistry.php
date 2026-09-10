<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Registry;

use Modules\Comms\Domain\Contracts\MeetingProviderDriver;

/**
 * Book I COM-07 §2. Mirrors `Modules\Core\Domain\Registry\NotificationChannelDriverRegistry`'s
 * shape, keyed by every provider string a driver's own `providers()`
 * claims to serve. Unlike that registry, there is no null-object
 * fallback here — a school with no configured meeting provider driver
 * genuinely cannot schedule a real meeting, and callers should get a
 * clear exception, not a silent no-op scheduling action.
 */
final class MeetingProviderDriverRegistry
{
    /**
     * @var array<string, MeetingProviderDriver>
     */
    private static array $drivers = [];

    public static function register(MeetingProviderDriver $driver): void
    {
        foreach ($driver->providers() as $provider) {
            self::$drivers[$provider] = $driver;
        }
    }

    public static function forProvider(string $provider): ?MeetingProviderDriver
    {
        return self::$drivers[$provider] ?? null;
    }

    public static function clear(): void
    {
        self::$drivers = [];
    }
}
