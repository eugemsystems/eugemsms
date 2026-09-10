<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Events;

use Modules\Security\Models\OccurrenceBookEntry;

final class OccurrenceRequiresImmediateNotice
{
    public function __construct(
        public readonly OccurrenceBookEntry $entry,
    ) {}
}
