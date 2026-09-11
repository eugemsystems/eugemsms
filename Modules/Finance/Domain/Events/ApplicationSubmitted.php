<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\ScholarshipApplication;

final class ApplicationSubmitted
{
    public function __construct(
        public readonly ScholarshipApplication $application,
    ) {}
}
