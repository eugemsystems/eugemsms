<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Schools;

use Modules\Core\Models\School;

final class SchoolCreated
{
    public function __construct(
        public readonly School $school,
    ) {}
}
