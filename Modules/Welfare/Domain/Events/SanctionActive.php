<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\Sanction;

final class SanctionActive
{
    public function __construct(
        public readonly Sanction $sanction,
    ) {}
}
