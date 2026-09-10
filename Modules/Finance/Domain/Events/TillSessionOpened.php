<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\TillSession;

final class TillSessionOpened
{
    public function __construct(
        public readonly TillSession $session,
    ) {}
}
