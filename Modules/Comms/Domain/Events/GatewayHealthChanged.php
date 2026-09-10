<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\MessageGateway;

final class GatewayHealthChanged
{
    public function __construct(
        public readonly MessageGateway $gateway,
        public readonly string $previousStatus,
    ) {}
}
