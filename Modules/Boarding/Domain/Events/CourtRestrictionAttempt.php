<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\CollectionAttempt;

final class CourtRestrictionAttempt
{
    public function __construct(
        public readonly CollectionAttempt $attempt,
    ) {}
}
