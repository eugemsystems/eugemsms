<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Schools;

use Modules\Core\Models\SchoolClass;

final class ClassCreated
{
    public function __construct(
        public readonly SchoolClass $class,
    ) {}
}
