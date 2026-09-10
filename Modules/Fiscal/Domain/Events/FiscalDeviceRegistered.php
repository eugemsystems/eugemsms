<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

use Modules\Fiscal\Models\FiscalDevice;

final class FiscalDeviceRegistered
{
    public function __construct(
        public readonly FiscalDevice $device,
    ) {}
}
