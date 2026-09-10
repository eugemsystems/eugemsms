<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Schools;

use Modules\Core\Models\School;

final class SchoolArchived
{
    public function __construct(
        public readonly School $school,
        public readonly string $reason,
    ) {}
}
