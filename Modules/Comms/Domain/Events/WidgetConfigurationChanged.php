<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\SchoolWidgetSetting;

final class WidgetConfigurationChanged
{
    public function __construct(
        public readonly SchoolWidgetSetting $setting,
    ) {}
}
