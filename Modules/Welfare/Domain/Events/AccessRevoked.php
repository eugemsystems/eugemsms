<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\CaseAccessGrant;

final class AccessRevoked
{
    public function __construct(
        public readonly CaseAccessGrant $grant,
    ) {}
}
