<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\HardwareDevice;

/**
 * ⚠ Book J INT-04 §5/BR-INT-04-009 (AC-INT-04-004).
 */
final class HardwareDeviceOffline
{
    public function __construct(
        public readonly HardwareDevice $device,
    ) {}
}
