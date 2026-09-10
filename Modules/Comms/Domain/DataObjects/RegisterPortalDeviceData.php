<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class RegisterPortalDeviceData
{
    public function __construct(
        public int $userId,
        public string $deviceId,
        public string $platform,
        public ?string $pushToken = null,
        public ?string $appVersion = null,
        public ?string $osVersion = null,
    ) {}
}
