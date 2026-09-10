<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\ProjectBrief;

final class ProjectBriefIssued
{
    public function __construct(
        public readonly ProjectBrief $brief,
    ) {}
}
