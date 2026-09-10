<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

use Modules\Welfare\Models\SafeguardingConcern;

final class ImmediateRiskConcern
{
    public function __construct(
        public readonly SafeguardingConcern $concern,
    ) {}
}
