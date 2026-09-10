<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\StaffWellbeingIndicator;

/**
 * ⚠ Book J INT-03 §5. Dispatched only when `flag_level` reaches
 * `concern` — never for `watch` or `none`.
 */
final class StaffWellbeingConcern
{
    public function __construct(
        public readonly StaffWellbeingIndicator $indicator,
    ) {}
}
