<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\LearnerProject;

final class LearnerProjectVerified
{
    public function __construct(
        public readonly LearnerProject $learnerProject,
    ) {}
}
