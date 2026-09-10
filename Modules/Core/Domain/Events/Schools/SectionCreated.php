<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Schools;

use Modules\Core\Models\SchoolSection;

final class SectionCreated
{
    public function __construct(
        public readonly SchoolSection $section,
    ) {}
}
