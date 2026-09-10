<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Events;

use Modules\Sport\Models\EquipmentIssue;

final class EquipmentOverdue
{
    public function __construct(
        public readonly EquipmentIssue $issue,
    ) {}
}
