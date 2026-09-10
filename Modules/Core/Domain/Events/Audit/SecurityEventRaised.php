<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Audit;

use Modules\Core\Models\SecurityEvent;

final class SecurityEventRaised
{
    public function __construct(
        public readonly SecurityEvent $event,
    ) {}
}
