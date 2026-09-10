<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Domain\DataObjects\ImportZimsecResultsResult;
use Modules\Compliance\Models\ZimsecRegistration;

final class ZimsecResultsImported
{
    public function __construct(
        public readonly ZimsecRegistration $registration,
        public readonly ImportZimsecResultsResult $result,
    ) {}
}
