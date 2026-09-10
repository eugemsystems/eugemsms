<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\Sanction;

final class SanctionOverturned
{
    public function __construct(
        public readonly Sanction $sanction,
    ) {}
}
