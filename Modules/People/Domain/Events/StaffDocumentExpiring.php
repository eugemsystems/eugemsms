<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\StaffDocument;

final class StaffDocumentExpiring
{
    public function __construct(
        public readonly StaffDocument $document,
        public readonly int $daysRemaining,
    ) {}
}
