<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\SenderId;

final class SenderIdRegistered
{
    public function __construct(
        public readonly SenderId $senderId,
    ) {}
}
