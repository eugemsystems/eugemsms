<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\PortalDevice;

final class DeviceRegistered
{
    public function __construct(
        public readonly PortalDevice $device,
    ) {}
}
