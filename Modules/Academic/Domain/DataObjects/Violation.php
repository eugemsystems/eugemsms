<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Modules\Academic\Models\SubjectSelectionRule;

final readonly class Violation
{
    public function __construct(
        public string $severity,
        public string $message,
        public SubjectSelectionRule $rule,
    ) {}
}
