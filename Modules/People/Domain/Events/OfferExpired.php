<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Application;

final class OfferExpired
{
    public function __construct(
        public readonly Application $application,
        public readonly ?Application $promoted = null,
    ) {}
}
