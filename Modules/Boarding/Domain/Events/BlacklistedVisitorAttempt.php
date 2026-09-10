<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\Visitor;

final class BlacklistedVisitorAttempt
{
    public function __construct(
        public readonly Visitor $visitor,
    ) {}
}
