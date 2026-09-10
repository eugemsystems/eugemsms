<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Application;

final class ApplicationOffered
{
    public function __construct(
        public readonly Application $application,
    ) {}
}
