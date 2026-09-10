<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Events;

use Modules\Security\Models\KeyIssue;

final class KeyOverdue
{
    public function __construct(
        public readonly KeyIssue $issue,
    ) {}
}
